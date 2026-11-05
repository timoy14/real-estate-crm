<?php

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PipelineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PipelineSeeder::class);
    $this->anna = User::factory()->create(['name' => 'Anna Reyes']);
    $this->ben = User::factory()->create(['name' => 'Ben Carter']);
    $this->travelTo(Carbon::parse('2026-11-04 09:00', 'America/New_York'));
});

function viewingFor(User $u, string $at, array $extra = []): Activity
{
    return Activity::factory()->viewing()->create(array_merge([
        'owner_id' => $u->id,
        'contact_id' => Contact::factory()->create(['owner_id' => $u->id])->id,
        'scheduled_at' => Carbon::parse($at, 'America/New_York'),
    ], $extra));
}

test("shows only today's scheduled viewings visible to the agent", function () {
    viewingFor($this->anna, '2026-11-04 15:00');
    viewingFor($this->anna, '2026-11-04 10:00');
    viewingFor($this->anna, '2026-11-05 10:00');
    viewingFor($this->anna, '2026-11-04 11:00', ['viewing_status' => 'cancelled']);
    viewingFor($this->anna, '2026-11-04 23:30');

    $this->actingAs($this->anna)->get('/app/my-day')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('app/MyDay')
            ->has('viewingsToday', 3)
            ->has('viewingsToday.0.property.title')
            ->has('viewingsToday.0.contact.name')
            ->where('viewingsToday.0.scheduled_at', Carbon::parse('2026-11-04 10:00', 'America/New_York')->toIso8601String()));
});

test("excludes ben's viewings", function () {
    viewingFor($this->ben, '2026-11-04 10:00');

    $this->actingAs($this->anna)->get('/app/my-day')
        ->assertInertia(fn (Assert $p) => $p->has('viewingsToday', 0));
});

test('overdue tasks exclude completed ones', function () {
    Task::factory()->create(['owner_id' => $this->anna->id, 'title' => 'late', 'due_at' => now()->subDay()]);
    Task::factory()->create(['owner_id' => $this->anna->id, 'title' => 'done', 'due_at' => now()->subDay(), 'completed_at' => now()]);
    Task::factory()->create(['owner_id' => $this->anna->id, 'title' => 'later today', 'due_at' => now()->setTime(17, 0)]);
    Task::factory()->create(['owner_id' => $this->ben->id, 'title' => 'bens', 'due_at' => now()->subDay()]);

    $this->actingAs($this->anna)->get('/app/my-day')
        ->assertInertia(fn (Assert $p) => $p
            ->has('overdueTasks', 1)->where('overdueTasks.0.title', 'late')
            ->has('todayTasks', 1)->where('todayTasks.0.title', 'later today'));
});

test('recent activity is limited to 10 visible items by others', function () {
    $mine = Contact::factory()->create(['owner_id' => $this->anna->id]);
    Activity::factory()->count(12)->create(['owner_id' => $this->ben->id, 'contact_id' => $mine->id]);
    Activity::factory()->create(['owner_id' => $this->anna->id, 'contact_id' => $mine->id, 'subject' => 'mine']);
    Activity::factory()->create(['owner_id' => $this->ben->id]); // Ben's own client

    $this->actingAs($this->anna)->get('/app/my-day')
        ->assertInertia(fn (Assert $p) => $p->has('recentActivity', 10));
});

test('stats counts match seeded data', function () {
    Deal::factory()->count(2)->create(['owner_id' => $this->anna->id]);
    Deal::factory()->create(['owner_id' => $this->anna->id, 'status' => 'won']);
    Deal::factory()->create(['owner_id' => $this->ben->id]);
    viewingFor($this->anna, '2026-11-04 10:00');
    viewingFor($this->anna, '2026-11-06 10:00');
    viewingFor($this->anna, '2026-11-09 10:00'); // next week (Mon Nov 2 - Sun Nov 8 is this week)
    viewingFor($this->ben, '2026-11-05 10:00');
    Task::factory()->create(['owner_id' => $this->anna->id, 'due_at' => now()->setTime(16, 0)]);
    Task::factory()->create(['owner_id' => $this->anna->id, 'due_at' => now()->subDay()]);

    $this->actingAs($this->anna)->get('/app/my-day')
        ->assertInertia(fn (Assert $p) => $p
            ->where('stats.openDeals', 2)
            ->where('stats.viewingsThisWeek', 2)
            ->where('stats.tasksDueToday', 1));
});
