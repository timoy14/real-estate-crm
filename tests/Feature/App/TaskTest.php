<?php

use App\Enums\Role;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
});

test('index groups overdue/today/upcoming in America/New_York', function () {
    // 23:30 on Nov 1 in New York is already Nov 2 in UTC.
    $this->travelTo(Carbon::parse('2026-11-01 23:30', 'America/New_York'));
    $mk = fn (string $title, ?string $due, array $extra = []) => Task::factory()->create(array_merge([
        'title' => $title, 'owner_id' => $this->anna->id,
        'due_at' => $due ? Carbon::parse($due, 'America/New_York') : null,
    ], $extra));

    $mk('overdue', '2026-11-01 22:00');
    $mk('today', '2026-11-01 23:59');
    $mk('upcoming', '2026-11-02 00:10');
    $mk('nodate', null);
    $mk('done recently', '2026-10-30 10:00', ['completed_at' => now()->subDays(2)]);
    $mk('done long ago', '2026-10-01 10:00', ['completed_at' => now()->subDays(10)]);
    Task::factory()->create(['title' => 'bens', 'owner_id' => $this->ben->id]);

    $this->actingAs($this->anna)->get('/app/tasks')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('app/Tasks/Index')
            ->where('groups.overdue.0.title', 'overdue')->has('groups.overdue', 1)
            ->where('groups.today.0.title', 'today')->has('groups.today', 1)
            ->where('groups.upcoming.0.title', 'upcoming')->has('groups.upcoming', 1)
            ->where('groups.no_date.0.title', 'nodate')->has('groups.no_date', 1)
            ->where('groups.completed.0.title', 'done recently')->has('groups.completed', 1));
});

test('index sorts by due date and admin sees every task', function () {
    $this->travelTo(Carbon::parse('2026-11-01 09:00', 'America/New_York'));
    Task::factory()->create(['owner_id' => $this->anna->id, 'title' => 'b', 'due_at' => now()->addDays(3)]);
    Task::factory()->create(['owner_id' => $this->ben->id, 'title' => 'a', 'due_at' => now()->addDays(2)]);

    $this->actingAs($this->admin)->get('/app/tasks')
        ->assertInertia(fn (Assert $p) => $p->has('groups.upcoming', 2)->where('groups.upcoming.0.title', 'a')->has('agents'));
    $this->actingAs($this->anna)->get('/app/tasks')
        ->assertInertia(fn (Assert $p) => $p->has('groups.upcoming', 1)->missing('agents'));
});

test('store creates a task, a date-only due date means end of that day', function () {
    $client = Contact::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/tasks', [
        'title' => 'Call back', 'due_at' => '2026-11-05', 'contact_id' => $client->id,
    ])->assertRedirect();

    $t = Task::firstOrFail();
    expect($t->owner_id)->toBe($this->anna->id)
        ->and($t->priority->value)->toBe('normal')
        ->and($t->due_at->format('Y-m-d H:i'))->toBe('2026-11-05 23:59')
        ->and($t->contact_id)->toBe($client->id);
});

test('task cannot link to another agent\'s client', function () {
    $others = Contact::factory()->for($this->ben, 'owner')->create();

    $this->actingAs($this->anna)->post('/app/tasks', ['title' => 'x', 'contact_id' => $others->id])
        ->assertSessionHasErrors('contact_id');
    expect(Task::count())->toBe(0);
});

test('toggle complete sets and clears completed_at', function () {
    $this->travelTo(Carbon::parse('2026-11-03 10:00', 'America/New_York'));
    $task = Task::factory()->create(['owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->patch("/app/tasks/{$task->id}", ['completed' => true])->assertRedirect();
    expect($task->fresh()->completed_at->format('Y-m-d H:i'))->toBe('2026-11-03 10:00');

    $this->actingAs($this->anna)->patch("/app/tasks/{$task->id}", ['completed' => false])->assertRedirect();
    expect($task->fresh()->completed_at)->toBeNull();
});

test('update edits fields and destroy soft deletes', function () {
    $task = Task::factory()->create(['owner_id' => $this->anna->id]);

    $this->actingAs($this->anna)->patch("/app/tasks/{$task->id}", ['title' => 'New title', 'priority' => 'high'])->assertRedirect();
    expect($task->fresh()->title)->toBe('New title')->and($task->fresh()->priority->value)->toBe('high');

    $this->actingAs($this->anna)->delete("/app/tasks/{$task->id}")->assertRedirect();
    expect(Task::withTrashed()->find($task->id)->deleted_at)->not->toBeNull();
});

test('403 on another agent\'s task', function () {
    $task = Task::factory()->create(['owner_id' => $this->ben->id]);

    $this->actingAs($this->anna)->patch("/app/tasks/{$task->id}", ['completed' => true])->assertForbidden();
    $this->actingAs($this->anna)->delete("/app/tasks/{$task->id}")->assertForbidden();
    expect($task->fresh()->completed_at)->toBeNull()->and($task->fresh()->trashed())->toBeFalse();
});

test('agent cannot assign a task to ben (forced to self)', function () {
    $this->actingAs($this->anna)->post('/app/tasks', ['title' => 'Mine', 'owner_id' => $this->ben->id])->assertRedirect();
    expect(Task::firstOrFail()->owner_id)->toBe($this->anna->id);

    $task = Task::firstOrFail();
    $this->actingAs($this->anna)->patch("/app/tasks/{$task->id}", ['owner_id' => $this->ben->id])->assertRedirect();
    expect($task->fresh()->owner_id)->toBe($this->anna->id);
});

test('admin can assign to ben', function () {
    $this->actingAs($this->admin)->post('/app/tasks', ['title' => 'For Ben', 'owner_id' => $this->ben->id])->assertRedirect();
    expect(Task::firstOrFail()->owner_id)->toBe($this->ben->id);

    $task = Task::firstOrFail();
    $this->actingAs($this->admin)->patch("/app/tasks/{$task->id}", ['owner_id' => $this->anna->id])->assertRedirect();
    expect($task->fresh()->owner_id)->toBe($this->anna->id);
});

test('client show lists only its own visible open tasks', function () {
    $client = Contact::factory()->for($this->anna, 'owner')->create();
    Task::factory()->create(['owner_id' => $this->anna->id, 'contact_id' => $client->id, 'title' => 'Linked']);
    Task::factory()->create(['owner_id' => $this->anna->id, 'title' => 'Unlinked']);
    Task::factory()->create(['owner_id' => $this->admin->id, 'contact_id' => $client->id, 'title' => 'Admin only']);

    $this->actingAs($this->anna)->get("/app/clients/{$client->id}")
        ->assertInertia(fn (Assert $p) => $p->has('tasks', 1)->where('tasks.0.title', 'Linked'));
});

// --- Final review fixes (minor: success toasts) ---

test('adding a task flashes a success toast', function () {
    $this->actingAs($this->anna)->post('/app/tasks', ['title' => 'Call Maria', 'priority' => 'normal'])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Task added.']);
});
