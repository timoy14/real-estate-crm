<?php

use App\Enums\ListingType;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
});

test('agent logs a call on own client and it appears in client show activities', function () {
    $client = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/activities', [
        'type' => 'call', 'body' => 'Discussed budget', 'call_outcome' => 'connected', 'contact_id' => $client->id,
    ])->assertRedirect();

    $a = Activity::firstOrFail();
    expect($a->owner_id)->toBe($this->anna->id)
        ->and($a->occurred_at)->not->toBeNull()
        ->and($a->viewing_status)->toBeNull();

    $this->actingAs($this->anna)->get("/app/clients/{$client->id}")
        ->assertInertia(fn (Assert $p) => $p->component('app/Clients/Show')
            ->has('activities', 1)
            ->where('activities.0.type', 'call')
            ->where('activities.0.body', 'Discussed budget')
            ->where('activities.0.author.name', 'Anna Reyes'));
});

test('422 logging on another agent\'s client', function () {
    $others = Contact::factory()->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/activities', [
        'type' => 'note', 'body' => 'x', 'contact_id' => $others->id,
    ])->assertSessionHasErrors('contact_id');

    expect(Activity::count())->toBe(0);
});

test('an activity needs a contact, property or deal', function () {
    $this->actingAs($this->anna)->post('/app/activities', ['type' => 'note', 'body' => 'x'])
        ->assertSessionHasErrors('contact_id');
});

test('schedule viewing requires property, contact and scheduled_at', function () {
    $this->actingAs($this->anna)->post('/app/activities', ['type' => 'viewing'])
        ->assertSessionHasErrors(['property_id', 'contact_id', 'scheduled_at']);

    $client = Contact::factory()->for($this->anna, 'owner')->create();
    $property = Property::factory()->create();

    $this->actingAs($this->anna)->post('/app/activities', [
        'type' => 'viewing', 'contact_id' => $client->id, 'property_id' => $property->id,
        'scheduled_at' => '2026-11-10 14:00',
    ])->assertRedirect();

    $a = Activity::firstOrFail();
    expect($a->viewing_status->value)->toBe('scheduled')
        ->and($a->occurred_at)->toBeNull()
        ->and($a->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-10 14:00');
});

test('completing a viewing stores feedback and occurred_at', function () {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(15, 0));
    $viewing = Activity::factory()->viewing()->create(['owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->patch("/app/activities/{$viewing->id}", [
        'viewing_status' => 'completed', 'feedback' => 'Loved the kitchen',
    ])->assertRedirect();

    $viewing->refresh();
    expect($viewing->viewing_status->value)->toBe('completed')
        ->and($viewing->feedback)->toBe('Loved the kitchen')
        ->and($viewing->occurred_at->format('Y-m-d H:i'))->toBe('2026-11-12 15:00');
});

test('cancelling a viewing sets cancelled', function () {
    $viewing = Activity::factory()->viewing()->create(['owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->patch("/app/activities/{$viewing->id}", ['viewing_status' => 'cancelled'])
        ->assertRedirect();

    expect($viewing->fresh()->viewing_status->value)->toBe('cancelled');
});

test('only the author or an admin can edit or delete an activity', function () {
    $contact = Contact::factory()->for($this->anna, 'owner')->create();
    $byAdmin = Activity::factory()->create(['owner_id' => $this->admin->id, 'contact_id' => $contact->id]);

    // Anna can see it (her client) but not change it.
    $this->actingAs($this->anna)->patch("/app/activities/{$byAdmin->id}", ['body' => 'hack'])->assertForbidden();
    $this->actingAs($this->anna)->delete("/app/activities/{$byAdmin->id}")->assertForbidden();

    $mine = Activity::factory()->create(['owner_id' => $this->anna->id, 'contact_id' => $contact->id]);
    $this->actingAs($this->anna)->patch("/app/activities/{$mine->id}", ['body' => 'edited'])->assertRedirect();
    expect($mine->fresh()->body)->toBe('edited');

    $this->actingAs($this->admin)->delete("/app/activities/{$mine->id}")->assertRedirect();
    expect(Activity::find($mine->id))->toBeNull()
        ->and(Activity::withTrashed()->find($mine->id))->not->toBeNull();
});

test('agent sees an activity authored by admin on the agent\'s own client', function () {
    $client = Contact::factory()->for($this->anna, 'owner')->create();
    $a = Activity::factory()->create(['owner_id' => $this->admin->id, 'contact_id' => $client->id, 'subject' => 'Admin check-in']);

    expect(Activity::visibleTo($this->anna)->pluck('id')->all())->toBe([$a->id]);

    $this->actingAs($this->anna)->get("/app/clients/{$client->id}")
        ->assertInertia(fn (Assert $p) => $p->has('activities', 1)->where('activities.0.subject', 'Admin check-in')
            ->where('activities.0.can.update', false));
});

test('agent cannot see ben\'s note on ben\'s client', function () {
    $bens = Contact::factory()->for($this->ben, 'owner')->create();
    $property = Property::factory()->create();
    $note = Activity::factory()->create([
        'owner_id' => $this->ben->id, 'contact_id' => $bens->id, 'property_id' => $property->id, 'type' => 'note',
    ]);

    expect(Activity::visibleTo($this->anna)->count())->toBe(0)
        ->and(Activity::visibleTo($this->ben)->count())->toBe(1)
        ->and(Activity::visibleTo($this->admin)->count())->toBe(1)
        ->and($this->anna->can('view', $note))->toBeFalse();

    $this->actingAs($this->anna)->get("/app/clients/{$bens->id}")->assertForbidden();
    $this->actingAs($this->anna)->get("/app/properties/{$property->id}")
        ->assertInertia(fn (Assert $p) => $p->has('activities', 0));
    $this->actingAs($this->anna)->patch("/app/activities/{$note->id}", ['body' => 'x'])->assertForbidden();
});

test('deal move writes a \'Moved to Offer\' note', function () {
    $sales = Pipeline::forListingType(ListingType::Sale);
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);
    $deal->contact->update(['owner_id' => $this->anna->id]);
    $offer = $sales->stages()->where('name', 'Offer')->first();

    $this->actingAs($this->anna)->patch("/app/deals/{$deal->id}/move", ['stage_id' => $offer->id, 'position' => 0])
        ->assertRedirect();

    $a = Activity::firstOrFail();
    expect($a->type->value)->toBe('note')
        ->and($a->subject)->toBe('Moved to Offer')
        ->and($a->deal_id)->toBe($deal->id)
        ->and($a->contact_id)->toBe($deal->contact_id)
        ->and($a->owner_id)->toBe($this->anna->id)
        ->and($a->occurred_at)->not->toBeNull();

    $this->actingAs($this->anna)->get("/app/deals/{$deal->id}")
        ->assertInertia(fn (Assert $p) => $p->has('activities', 1)->where('activities.0.subject', 'Moved to Offer'));

    // A reorder inside the same column logs nothing.
    $this->actingAs($this->anna)->patch("/app/deals/{$deal->id}/move", ['stage_id' => $offer->id, 'position' => 0]);
    expect(Activity::count())->toBe(1);
});

test('admin moving a deal is credited as the author', function () {
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id]);
    $stage = Pipeline::forListingType(ListingType::Sale)->stages()->where('name', 'Viewing')->first();

    $this->actingAs($this->admin)->patch("/app/deals/{$deal->id}/move", ['stage_id' => $stage->id, 'position' => 0]);

    expect(Activity::firstOrFail()->owner_id)->toBe($this->admin->id);
});

test('agent cannot change activity links on update', function () {
    $mine = Contact::factory()->for($this->anna, 'owner')->create();
    $bens = Contact::factory()->for($this->ben, 'owner')->create();
    $bensDeal = Deal::factory()->create(['owner_id' => $this->ben->id, 'contact_id' => $bens->id]);
    $a = Activity::factory()->create(['owner_id' => $this->anna->id, 'contact_id' => $mine->id, 'type' => 'note']);

    $this->actingAs($this->anna)->patch("/app/activities/{$a->id}", ['body' => 'x', 'contact_id' => $bens->id])
        ->assertSessionHasErrors('contact_id');
    $this->actingAs($this->anna)->patch("/app/activities/{$a->id}", ['body' => 'x', 'deal_id' => $bensDeal->id])
        ->assertSessionHasErrors('deal_id');

    $a->refresh();
    expect($a->contact_id)->toBe($mine->id)->and($a->deal_id)->toBeNull();
});

test('logging on a deal does not copy a contact the agent cannot see', function () {
    $bens = Contact::factory()->for($this->ben, 'owner')->create();
    $deal = Deal::factory()->create(['owner_id' => $this->anna->id, 'contact_id' => $bens->id]);

    $this->actingAs($this->anna)->post('/app/activities', ['type' => 'note', 'body' => 'x', 'deal_id' => $deal->id])
        ->assertSessionHasNoErrors();

    expect(Activity::firstOrFail()->contact_id)->toBeNull();
});

// --- Final review fixes (B-M8) ---

test('moving a completed viewing back to scheduled or cancelled clears occurred_at', function (string $status) {
    $viewing = Activity::factory()->viewing()->create(['owner_id' => $this->anna->id]);
    $this->actingAs($this->anna)->patch("/app/activities/{$viewing->id}", ['viewing_status' => 'completed']);
    expect($viewing->fresh()->occurred_at)->not->toBeNull();

    $this->actingAs($this->anna)->patch("/app/activities/{$viewing->id}", ['viewing_status' => $status])
        ->assertSessionHasNoErrors();

    $viewing->refresh();
    expect($viewing->viewing_status->value)->toBe($status)->and($viewing->occurred_at)->toBeNull();
})->with(['scheduled', 'cancelled']);

// --- Final review fixes (minor: success toasts) ---

test('logging an activity and scheduling a viewing flash a success toast', function () {
    $client = Contact::factory()->for($this->anna, 'owner')->create();
    $property = Property::factory()->create();

    $this->actingAs($this->anna)->post('/app/activities', ['type' => 'note', 'body' => 'Called back', 'contact_id' => $client->id])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Activity logged.']);
    $this->actingAs($this->anna)->post('/app/activities', [
        'type' => 'viewing', 'contact_id' => $client->id, 'property_id' => $property->id, 'scheduled_at' => '2026-11-10 14:00',
    ])->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Viewing scheduled.']);
});
