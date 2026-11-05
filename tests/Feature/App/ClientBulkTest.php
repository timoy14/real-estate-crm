<?php

use App\Enums\Role;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
});

test('admin reassigns 3 clients to ben', function () {
    $ids = Contact::factory()->count(3)->for($this->anna, 'owner')->create()->pluck('id')->all();

    $this->actingAs($this->admin)->post('/app/clients/bulk', [
        'ids' => $ids, 'action' => 'reassign', 'owner_id' => $this->ben->id,
    ])->assertRedirect();

    expect(Contact::whereIn('id', $ids)->where('owner_id', $this->ben->id)->count())->toBe(3);
});

test('agent cannot reassign (403)', function () {
    $ids = Contact::factory()->count(2)->for($this->anna, 'owner')->create()->pluck('id')->all();

    $this->actingAs($this->anna)->post('/app/clients/bulk', [
        'ids' => $ids, 'action' => 'reassign', 'owner_id' => $this->ben->id,
    ])->assertForbidden();

    expect(Contact::where('owner_id', $this->anna->id)->count())->toBe(2);
});

test('agent bulk-deleting includes another agent\'s id gives 403 and nothing deleted', function () {
    $mine = Contact::factory()->count(2)->for($this->anna, 'owner')->create()->pluck('id')->all();
    $theirs = Contact::factory()->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/clients/bulk', [
        'ids' => [...$mine, $theirs->id], 'action' => 'delete',
    ])->assertForbidden();

    expect(Contact::count())->toBe(3);
});

test('agent can bulk delete own clients', function () {
    $mine = Contact::factory()->count(2)->for($this->anna, 'owner')->create()->pluck('id')->all();

    $this->actingAs($this->anna)->post('/app/clients/bulk', ['ids' => $mine, 'action' => 'delete'])
        ->assertRedirect();

    expect(Contact::count())->toBe(0);
});

test('set_status updates only own clients for agent', function () {
    $mine = Contact::factory()->count(2)->for($this->anna, 'owner')->create(['status' => 'new_lead']);
    $theirs = Contact::factory()->for($this->ben, 'owner')->create(['status' => 'new_lead']);

    $this->actingAs($this->anna)->post('/app/clients/bulk', [
        'ids' => $mine->pluck('id')->all(), 'action' => 'set_status', 'status' => 'active',
    ])->assertRedirect();
    expect(Contact::where('status', 'active')->count())->toBe(2);

    $this->actingAs($this->anna)->post('/app/clients/bulk', [
        'ids' => [$theirs->id], 'action' => 'set_status', 'status' => 'active',
    ])->assertForbidden();
    expect($theirs->fresh()->status->value)->toBe('new_lead');
});

test('bulk validates action and status', function () {
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $this->actingAs($this->anna)->post('/app/clients/bulk', ['ids' => [$c->id], 'action' => 'nuke'])
        ->assertSessionHasErrors('action');
    $this->actingAs($this->anna)->post('/app/clients/bulk', ['ids' => [$c->id], 'action' => 'set_status', 'status' => 'bogus'])
        ->assertSessionHasErrors('status');
});

// --- Final review fixes (B-C1, B-I2) ---

test('bulk delete fails as a whole when any client has open deals', function () {
    $this->seed(\Database\Seeders\PipelineSeeder::class);
    $free = Contact::factory()->for($this->anna, 'owner')->create();
    $busy = Contact::factory()->for($this->anna, 'owner')->create();
    \App\Models\Deal::factory()->create(['contact_id' => $busy->id, 'owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->from('/app/clients')->post('/app/clients/bulk', [
        'ids' => [$free->id, $busy->id], 'action' => 'delete',
    ])->assertRedirect('/app/clients')->assertSessionHasErrors('ids');

    expect(Contact::whereIn('id', [$free->id, $busy->id])->count())->toBe(2);
});

test('bulk reassign moves open deals, open tasks and inquiries too', function () {
    $this->seed(\Database\Seeders\PipelineSeeder::class);
    $c = Contact::factory()->for($this->anna, 'owner')->create();
    $open = \App\Models\Deal::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id]);
    $lost = \App\Models\Deal::factory()->inStage('Lost')->create([
        'contact_id' => $c->id, 'owner_id' => $this->anna->id, 'status' => 'lost', 'closed_at' => now(),
    ]);
    $task = \App\Models\Task::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id, 'completed_at' => null]);
    $inquiry = \App\Models\Inquiry::factory()->create(['contact_id' => $c->id, 'owner_id' => $this->anna->id]);

    $this->actingAs($this->admin)->post('/app/clients/bulk', [
        'ids' => [$c->id], 'action' => 'reassign', 'owner_id' => $this->ben->id,
    ])->assertSessionHasNoErrors();

    expect($open->fresh()->owner_id)->toBe($this->ben->id)
        ->and($lost->fresh()->owner_id)->toBe($this->anna->id)
        ->and($task->fresh()->owner_id)->toBe($this->ben->id)
        ->and($inquiry->fresh()->owner_id)->toBe($this->ben->id);
});

test('a bulk action flashes a success toast', function () {
    $ids = Contact::factory()->count(2)->for($this->anna, 'owner')->create()->pluck('id')->all();
    $this->actingAs($this->anna)->post('/app/clients/bulk', ['ids' => $ids, 'action' => 'set_status', 'status' => 'active'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '2 clients updated.']);
});
