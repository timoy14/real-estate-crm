<?php

use App\Enums\Role;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
});

function validClient(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'email' => 'Maria@Example.com',
        'roles' => ['buyer'],
    ], $overrides);
}

test('agent sees only own clients in index', function () {
    Contact::factory()->count(3)->for($this->anna, 'owner')->create();
    Contact::factory()->count(2)->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->get('/app/clients')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('app/Clients/Index')
            ->has('clients.data', 3)
            ->missing('agents'));
});

test('admin sees all clients and the agents list', function () {
    Contact::factory()->count(3)->for($this->anna, 'owner')->create();
    Contact::factory()->count(2)->for($this->ben, 'owner')->create();

    $this->actingAs($this->admin)->get('/app/clients')
        ->assertInertia(fn (Assert $p) => $p->has('clients.data', 5)->has('agents'));
});

test('agent gets 403 viewing, updating and deleting another agent\'s client', function () {
    $c = Contact::factory()->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->get("/app/clients/{$c->id}")->assertForbidden();
    $this->actingAs($this->anna)->patch("/app/clients/{$c->id}", validClient())->assertForbidden();
    $this->actingAs($this->anna)->delete("/app/clients/{$c->id}")->assertForbidden();
    expect(Contact::find($c->id))->not->toBeNull();
});

test('show returns client, wishlists, can and placeholders', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->get("/app/clients/{$c->id}")
        ->assertInertia(fn (Assert $p) => $p->component('app/Clients/Show')
            ->where('client.id', $c->id)
            ->where('can.update', true)
            ->where('can.delete', true)
            ->where('wishlists', [])
            ->where('matches', [])
            ->where('deals', [])
            ->where('activities', [])
            ->where('tasks', []));
});

test('store requires first_name, at least one role, and email or phone', function () {
    $this->actingAs($this->anna)->post('/app/clients', [])
        ->assertSessionHasErrors(['first_name', 'roles', 'email']);
    $this->actingAs($this->anna)->post('/app/clients', validClient(['roles' => []]))
        ->assertSessionHasErrors('roles');
    expect(Contact::count())->toBe(0);
});

test('store lowercases email and normalizes phone', function () {
    $this->actingAs($this->anna)->post('/app/clients', validClient(['phone' => '(555) 123-4567']))
        ->assertRedirect();
    $c = Contact::firstOrFail();
    expect($c->email)->toBe('maria@example.com')
        ->and($c->phone)->toBe('(555) 123-4567')
        ->and($c->phone_normalized)->toBe('5551234567')
        ->and($c->owner_id)->toBe($this->anna->id);
});

test('+1 555 123 4567 also normalizes to 5551234567', function () {
    $this->actingAs($this->anna)->post('/app/clients', validClient(['email' => null, 'phone' => '+1 555 123 4567']))
        ->assertSessionHasNoErrors();
    expect(Contact::firstOrFail()->phone_normalized)->toBe('5551234567');
});

test('agent cannot set owner_id to someone else', function () {
    $this->actingAs($this->anna)->post('/app/clients', validClient(['owner_id' => $this->ben->id]));
    expect(Contact::firstOrFail()->owner_id)->toBe($this->anna->id);
});

test('agent cannot reassign owner on update', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $this->actingAs($this->anna)->patch("/app/clients/{$c->id}", validClient(['owner_id' => $this->ben->id]))
        ->assertSessionHasNoErrors();
    expect($c->fresh()->owner_id)->toBe($this->anna->id);
});

test('admin can set and reassign owner', function () {
    $this->actingAs($this->admin)->post('/app/clients', validClient(['owner_id' => $this->ben->id]));
    $c = Contact::firstOrFail();
    expect($c->owner_id)->toBe($this->ben->id);

    $this->actingAs($this->admin)->patch("/app/clients/{$c->id}", validClient(['owner_id' => $this->anna->id]));
    expect($c->fresh()->owner_id)->toBe($this->anna->id);
});

test('search maria matches first_name case-insensitively', function () {
    Contact::factory()->for($this->anna, 'owner')->create(['first_name' => 'MARIA', 'email' => 'a@x.test']);
    Contact::factory()->for($this->anna, 'owner')->create(['first_name' => 'Joe', 'email' => 'j@x.test']);

    $this->actingAs($this->anna)->get('/app/clients?search=maria')
        ->assertInertia(fn (Assert $p) => $p->has('clients.data', 1)->where('filters.search', 'maria'));
});

test('search by phone digits matches normalized phone', function () {
    Contact::factory()->for($this->anna, 'owner')->create(['phone' => '(555) 123-4567']);
    Contact::factory()->for($this->anna, 'owner')->create(['phone' => '999 888 7777']);

    $this->actingAs($this->anna)->get('/app/clients?search=555-123')
        ->assertInertia(fn (Assert $p) => $p->has('clients.data', 1));
});

test('filter tag=vip returns only tagged', function () {
    Contact::factory()->for($this->anna, 'owner')->create(['tags' => ['vip']]);
    Contact::factory()->for($this->anna, 'owner')->create(['tags' => ['cold']]);

    $this->actingAs($this->anna)->get('/app/clients?tag=vip')
        ->assertInertia(fn (Assert $p) => $p->has('clients.data', 1)->where('clients.data.0.tags', ['vip']));
});

test('filter by role and status', function () {
    Contact::factory()->for($this->anna, 'owner')->create(['roles' => ['seller'], 'status' => 'active']);
    Contact::factory()->for($this->anna, 'owner')->create(['roles' => ['buyer'], 'status' => 'active']);
    Contact::factory()->for($this->anna, 'owner')->create(['roles' => ['seller'], 'status' => 'lost']);

    $this->actingAs($this->anna)->get('/app/clients?role=seller&status=active')
        ->assertInertia(fn (Assert $p) => $p->has('clients.data', 1));
});

test('sorting by name works and rejects unknown columns safely', function () {
    Contact::factory()->for($this->anna, 'owner')->create(['first_name' => 'Zed', 'last_name' => null]);
    Contact::factory()->for($this->anna, 'owner')->create(['first_name' => 'Amy', 'last_name' => null]);

    $this->actingAs($this->anna)->get('/app/clients?sort=name&direction=asc')
        ->assertInertia(fn (Assert $p) => $p->where('clients.data.0.name', 'Amy'));
    $this->actingAs($this->anna)->get('/app/clients?sort=password&direction=sideways')->assertOk();
});

test('destroy soft-deletes', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $this->actingAs($this->anna)->delete("/app/clients/{$c->id}")->assertRedirect('/app/clients');
    expect(Contact::find($c->id))->toBeNull()
        ->and(Contact::withTrashed()->find($c->id))->not->toBeNull();
});

test('owner_id is restricted on user delete', function () {
    Contact::factory()->for($this->anna, 'owner')->create();
    expect(fn () => $this->anna->delete())->toThrow(QueryException::class);
});

test('VisibleTo scope: agent own, admin all', function () {
    Contact::factory()->count(2)->for($this->anna, 'owner')->create();
    Contact::factory()->for($this->ben, 'owner')->create();
    expect(Contact::visibleTo($this->anna)->count())->toBe(2)
        ->and(Contact::visibleTo($this->admin)->count())->toBe(3);
});

// --- Final review fixes (B-C1, B-I2) ---

test('deleting a client with open deals is blocked with a friendly error', function () {
    $this->seed(\Database\Seeders\PipelineSeeder::class);
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    \App\Models\Deal::factory()->count(2)->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->from("/app/clients/{$c->id}")->delete("/app/clients/{$c->id}")
        ->assertRedirect("/app/clients/{$c->id}")
        ->assertSessionHasErrors(['client' => 'Close or reassign its 2 open deals first.']);
    expect(Contact::find($c->id))->not->toBeNull();
});

test('deleting a client with only closed deals succeeds and board, deal and property pages still render', function () {
    $this->seed(\Database\Seeders\PipelineSeeder::class);
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $property = \App\Models\Property::factory()->for($this->anna, 'listingAgent')->create();
    $deal = \App\Models\Deal::factory()->inStage('Closed Won')->create([
        'contact_id' => $c->id, 'owner_id' => $this->anna->id, 'property_id' => $property->id,
        'status' => 'won', 'closed_at' => now(),
    ]);

    $this->actingAs($this->anna)->delete("/app/clients/{$c->id}")->assertSessionHasNoErrors()->assertRedirect('/app/clients');
    expect(Contact::find($c->id))->toBeNull();

    $this->actingAs($this->anna)->get('/app/deals')->assertOk();
    $this->actingAs($this->anna)->get("/app/deals/{$deal->id}")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('deal.contact.id', $c->id));
    $this->actingAs($this->anna)->get("/app/properties/{$property->slug}")->assertOk();
    $this->actingAs($this->anna)->get("/app/clients/{$c->id}")->assertNotFound();
});

test('admin reassigning a client moves its open deals, open tasks and inquiries; closed deals keep their owner', function () {
    $this->seed(\Database\Seeders\PipelineSeeder::class);
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $open = \App\Models\Deal::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id]);
    $won = \App\Models\Deal::factory()->inStage('Closed Won')->create([
        'contact_id' => $c->id, 'owner_id' => $this->anna->id, 'status' => 'won', 'closed_at' => now(),
    ]);
    $openTask = \App\Models\Task::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id, 'completed_at' => null]);
    $doneTask = \App\Models\Task::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id, 'completed_at' => now()]);
    $inquiry = \App\Models\Inquiry::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id]);

    $this->actingAs($this->admin)->patch("/app/clients/{$c->id}", validClient(['owner_id' => $this->ben->id]))
        ->assertSessionHasNoErrors();

    expect($c->fresh()->owner_id)->toBe($this->ben->id)
        ->and($open->fresh()->owner_id)->toBe($this->ben->id)
        ->and($won->fresh()->owner_id)->toBe($this->anna->id)
        ->and($openTask->fresh()->owner_id)->toBe($this->ben->id)
        ->and($doneTask->fresh()->owner_id)->toBe($this->anna->id)
        ->and($inquiry->fresh()->owner_id)->toBe($this->ben->id);
});
