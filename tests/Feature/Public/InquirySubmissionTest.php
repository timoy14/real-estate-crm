<?php

use App\Mail\InquiryReceived;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Inquiry;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NewInquiryNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    RateLimiter::clear('inquiries');
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
    $this->property = Property::factory()->create([
        'title' => 'Brickell Loft', 'listing_type' => 'sale', 'price' => 450000,
        'is_published' => true, 'status' => 'available', 'listing_agent_id' => $this->anna->id,
    ]);
});

function payload(array $o = []): array
{
    return array_merge([
        'name' => 'Maria Lopez',
        'email' => 'maria@x.com',
        'phone' => '(555) 123-4567',
        'message' => 'Hi, I am interested.',
        'wants_viewing' => true,
        'preferred_contact' => 'email',
        'consent' => true,
        'website' => '',
    ], $o);
}

function submit($test, array $o = [], ?Property $p = null)
{
    return $test->post('/properties/'.($p ?? $test->property)->slug.'/inquiries', payload($o));
}

test('valid submission creates inquiry, contact, deal, high-priority task and notification', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'America/New_York'));
    Notification::fake();

    submit($this)->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

    $inquiry = Inquiry::sole();
    $contact = Contact::sole();
    $deal = Deal::sole();
    $task = Task::sole();

    expect($contact->owner_id)->toBe($this->anna->id)
        ->and($contact->roles->map->value->all())->toBe(['buyer'])
        ->and($inquiry->owner_id)->toBe($this->anna->id)
        ->and($inquiry->contact_id)->toBe($contact->id)
        ->and($inquiry->deal_id)->toBe($deal->id)
        ->and($inquiry->ip)->not->toBeNull()
        ->and($inquiry->source)->toBe('website')
        ->and($deal->title)->toBe('Maria Lopez – Brickell Loft')
        ->and($deal->value)->toBe(450000)
        ->and($deal->owner_id)->toBe($this->anna->id)
        ->and($deal->stage->name)->toBe('Inquiry')
        ->and($deal->pipeline->slug)->toBe('sales')
        ->and($deal->position)->toBe(0)
        ->and($task->title)->toBe('Contact Maria Lopez about Brickell Loft')
        ->and($task->priority->value)->toBe('high')
        ->and($task->owner_id)->toBe($this->anna->id)
        ->and($task->inquiry_id)->toBe($inquiry->id)
        ->and($task->due_at->setTimezone('America/New_York')->format('Y-m-d H:i'))->toBe('2026-11-04 11:00');

    $note = Activity::sole();
    expect($note->type->value)->toBe('note')
        ->and($note->body)->toContain('Website inquiry: Hi, I am interested.')
        ->and($note->body)->toContain('Wants a viewing.')
        ->and($note->owner_id)->toBe($this->anna->id);

    Notification::assertSentTo($this->anna, NewInquiryNotification::class, function ($n) use ($contact, $inquiry) {
        $data = $n->toArray($this->anna);

        return $data['url'] === '/app/clients/'.$contact->id && isset($data['title'], $data['body']) && $inquiry->exists;
    });
    Notification::assertCount(1);
});

test('second deal in the same stage gets the next position', function () {
    submit($this, ['email' => 'a@x.com', 'phone' => null])->assertSessionHasNoErrors();
    submit($this, ['email' => 'b@x.com', 'phone' => null])->assertSessionHasNoErrors();

    expect(Deal::orderBy('id')->pluck('position')->all())->toBe([0, 1]);
});

test('rent property creates a tenant contact in the Rentals pipeline', function () {
    $rent = Property::factory()->create([
        'listing_type' => 'rent', 'price' => 2000, 'is_published' => true, 'status' => 'available',
        'listing_agent_id' => $this->anna->id,
    ]);

    submit($this, [], $rent)->assertSessionHasNoErrors();

    expect(Contact::sole()->roles->map->value->all())->toBe(['tenant'])
        ->and(Deal::sole()->pipeline->slug)->toBe('rentals');
});

test('returning client owned by Ben keeps their agent, both are notified', function () {
    Notification::fake();
    Contact::factory()->for($this->ben, 'owner')->create(['email' => 'maria@x.com', 'phone' => null]);

    submit($this, ['email' => 'Maria@X.com'])->assertSessionHasNoErrors();

    expect(Contact::count())->toBe(1)
        ->and(Deal::sole()->owner_id)->toBe($this->ben->id)
        ->and(Task::sole()->owner_id)->toBe($this->ben->id)
        ->and(Inquiry::sole()->owner_id)->toBe($this->ben->id);
    Notification::assertSentTo($this->ben, NewInquiryNotification::class);
    Notification::assertSentTo($this->anna, NewInquiryNotification::class);
});

test('second inquiry with different casing and phone format reuses the contact', function () {
    submit($this, ['email' => 'maria@x.com', 'phone' => '555-123-4567']);
    submit($this, ['email' => 'Maria@X.com', 'phone' => '(555) 123-4567']);

    expect(Contact::count())->toBe(1)->and(Inquiry::count())->toBe(2)->and(Deal::count())->toBe(2);
});

test('confirmation mail is queued when an email is given', function () {
    Mail::fake();

    submit($this)->assertSessionHasNoErrors();

    Mail::assertQueued(InquiryReceived::class, fn ($m) => $m->hasTo('maria@x.com'));
});

test('no confirmation mail without an email', function () {
    Mail::fake();

    submit($this, ['email' => null])->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
});

test('missing email and phone is a validation error', function () {
    submit($this, ['email' => null, 'phone' => null])->assertSessionHasErrors(['email', 'phone']);
    expect(Inquiry::count())->toBe(0);
});

test('JSON request with invalid data returns 422', function () {
    $this->postJson('/properties/'.$this->property->slug.'/inquiries', payload(['email' => null, 'phone' => null]))
        ->assertStatus(422);
});

test('consent unchecked is a validation error', function () {
    submit($this, ['consent' => false])->assertSessionHasErrors('consent');
    expect(Inquiry::count())->toBe(0);
});

test('honeypot filled pretends success and stores nothing', function () {
    Notification::fake();
    Mail::fake();

    submit($this, ['website' => 'http://spam.example'])->assertRedirect()->assertSessionHas('success');

    expect(Inquiry::count())->toBe(0)->and(Contact::count())->toBe(0)->and(Deal::count())->toBe(0);
    Notification::assertNothingSent();
    Mail::assertNothingQueued();
});

test('sold property gives a property error and stores nothing', function () {
    $this->property->update(['status' => 'sold']);

    submit($this)->assertSessionHasErrors(['property' => 'Sorry, this property is no longer available.']);

    expect(Inquiry::count())->toBe(0)->and(Contact::count())->toBe(0);
});

test('unknown slug and numeric id give the friendly property error, nothing stored', function () {
    $this->post('/properties/nope/inquiries', payload())->assertSessionHasErrors('property');
    $this->post('/properties/'.$this->property->id.'/inquiries', payload())->assertSessionHasErrors('property');
    expect(Inquiry::count())->toBe(0);
});

test('soft-deleted property gives the friendly property error', function () {
    $slug = $this->property->slug;
    $this->property->delete();

    $this->post("/properties/{$slug}/inquiries", payload())
        ->assertSessionHasErrors(['property' => 'Sorry, this property is no longer available.']);
    expect(Inquiry::count())->toBe(0);
});

test('phone without enough digits is rejected', function () {
    submit($this, ['email' => null, 'phone' => 'abc'])->assertSessionHasErrors('phone');
    submit($this, ['email' => 'ok@x.com', 'phone' => '12345'])->assertSessionHasErrors('phone');
    submit($this, ['email' => null, 'phone' => '+1 (555) 123-4567'])->assertSessionHasNoErrors();
});

test('markdown and link injection in the name does not render a link in the mail', function () {
    $inquiry = Inquiry::factory()->create([
        'name' => '[Verify your account](https://evil.example/x)',
        'property_id' => $this->property->id,
        'owner_id' => $this->anna->id,
    ]);

    $html = (new InquiryReceived($inquiry))->render();

    expect($html)->not->toContain('href="https://evil.example');
});

test('the processing transaction takes an advisory lock on the normalized email', function () {
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = [$q->sql, $q->bindings];
    });

    submit($this, ['email' => 'Maria@X.com'])->assertSessionHasNoErrors();

    $locks = array_values(array_filter($queries, fn ($q) => str_contains($q[0], 'pg_advisory_xact_lock')));
    expect($locks)->toHaveCount(1)
        ->and($locks[0][1])->toContain('email:maria@x.com');
});

test('the advisory lock falls back to the phone when there is no email', function () {
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = [$q->sql, $q->bindings];
    });

    submit($this, ['email' => null, 'phone' => '(555) 123-4567'])->assertSessionHasNoErrors();

    $locks = array_values(array_filter($queries, fn ($q) => str_contains($q[0], 'pg_advisory_xact_lock')));
    expect($locks[0][1])->toContain('phone:5551234567');
});

test('a failing notification after commit still returns success and keeps the inquiry', function () {
    Event::listen(NotificationSending::class, fn () => throw new RuntimeException('channel down'));

    submit($this)->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

    expect(Inquiry::count())->toBe(1)->and(Deal::count())->toBe(1);
});

test('a failing confirmation mail after commit still returns success', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    submit($this)->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();

    expect(Inquiry::count())->toBe(1);
});

test('listing agent who is not the deal owner gets a notification without visitor details', function () {
    Notification::fake();
    Contact::factory()->for($this->ben, 'owner')->create(['email' => 'maria@x.com', 'phone' => null]);

    submit($this, ['message' => 'My secret message'])->assertSessionHasNoErrors();

    $annas = Notification::sent($this->anna, NewInquiryNotification::class)->first()->toArray($this->anna);
    expect($annas['title'])->toBe('New inquiry on Brickell Loft')
        ->and($annas['body'])->toBe('A client of Ben Carter inquired about this property.')
        ->and($annas['url'])->toBe('/app/properties/'.$this->property->id)
        ->and(json_encode($annas))->not->toContain('Maria')->not->toContain('secret');

    $bens = Notification::sent($this->ben, NewInquiryNotification::class)->first()->toArray($this->ben);
    expect($bens['title'])->toBe('New inquiry from Maria Lopez')->and($bens['body'])->toContain('My secret message');
});

test('over-long and malformed input is rejected', function () {
    submit($this, ['message' => str_repeat('a', 2001)])->assertSessionHasErrors('message');
    submit($this, ['name' => str_repeat('a', 121)])->assertSessionHasErrors('name');
    submit($this, ['preferred_contact' => 'fax'])->assertSessionHasErrors('preferred_contact');
    submit($this, ['name' => ['a']])->assertSessionHasErrors('name');
});

test('client-supplied status, owner and ids are ignored', function () {
    submit($this, ['owner_id' => $this->ben->id, 'status' => 'converted', 'property_id' => 999, 'contact_id' => 999]);

    $i = Inquiry::sole();
    expect($i->owner_id)->toBe($this->anna->id)->and($i->status->value)->toBe('new')->and($i->property_id)->toBe($this->property->id);
});

test('script tags in the message are stored as plain text', function () {
    submit($this, ['message' => '<script>alert(1)</script>']);

    expect(Inquiry::sole()->message)->toBe('<script>alert(1)</script>');
});

test('6th submission within a minute from the same IP is 429', function () {
    foreach (range(1, 5) as $i) {
        submit($this, ['email' => "u$i@x.com"])->assertSessionHasNoErrors();
    }

    submit($this, ['email' => 'u6@x.com'])->assertStatus(429);
    expect(Inquiry::count())->toBe(5);
});

test('route keeps the web (CSRF) middleware and the inquiries throttle', function () {
    // Feature tests skip CSRF verification, so assert the middleware stack on the route.
    $route = app('router')->getRoutes()->getByName('listings.inquire');
    expect($route->gatherMiddleware())->toContain('web')->and($route->gatherMiddleware())->toContain('throttle:inquiries');
});

test('inquiry is not visible to guests via the app', function () {
    $this->get('/app/inquiries')->assertRedirect('/login');
});
