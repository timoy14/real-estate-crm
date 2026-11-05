<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

class TestNote extends Notification
{
    public function __construct(private string $title = 'Hello') {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return ['title' => $this->title, 'body' => 'Body', 'url' => '/app/my-day'];
    }
}

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->ben = User::factory()->create();
});

test('lists only own notifications', function () {
    $this->anna->notify(new TestNote('For Anna'));
    $this->ben->notify(new TestNote('For Ben'));

    $this->actingAs($this->anna)->getJson('/app/notifications')->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.title', 'For Anna')
        ->assertJsonPath('notifications.0.url', '/app/my-day')
        ->assertJsonPath('notifications.0.read', false)
        ->assertJsonPath('unread', 1);
});

test('returns at most the latest 20', function () {
    foreach (range(1, 22) as $i) {
        $this->anna->notify(new TestNote("n$i"));
    }
    $this->actingAs($this->anna)->getJson('/app/notifications')->assertJsonCount(20, 'notifications');
});

test('read marks one notification read and cannot touch others', function () {
    $this->anna->notify(new TestNote);
    $this->ben->notify(new TestNote);
    $mine = $this->anna->notifications()->first();
    $bens = $this->ben->notifications()->first();

    $this->actingAs($this->anna)->post("/app/notifications/{$bens->id}/read")->assertNotFound();
    expect($bens->fresh()->read_at)->toBeNull();

    $this->actingAs($this->anna)->post("/app/notifications/{$mine->id}/read")->assertSuccessful();
    expect($mine->fresh()->read_at)->not->toBeNull();
});

test('read-all marks them read', function () {
    $this->anna->notify(new TestNote);
    $this->anna->notify(new TestNote);
    $this->ben->notify(new TestNote);

    $this->actingAs($this->anna)->post('/app/notifications/read-all')->assertSuccessful();

    expect($this->anna->unreadNotifications()->count())->toBe(0)
        ->and($this->ben->unreadNotifications()->count())->toBe(1);
});

test('shared counts.unreadNotifications reflects unread', function () {
    $this->anna->notify(new TestNote);
    $this->anna->notify(new TestNote);
    $this->ben->notify(new TestNote);

    $this->actingAs($this->anna)->get('/app/my-day')
        ->assertInertia(fn (Assert $p) => $p->where('counts.unreadNotifications', 2)->where('counts.newInquiries', 0));
});

test('guests cannot read notifications', function () {
    $this->getJson('/app/notifications')->assertUnauthorized();
});

test('marking a non-uuid notification id read is a 404', function () {
    $this->actingAs($this->anna)->postJson('/app/notifications/not-a-uuid/read')->assertNotFound();
});

// --- Final review fixes (B-M9) ---

test('the unread notifications count is lazy: a partial reload that skips counts never queries notifications', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/app/my-day')->assertOk(); // warm up (version etc.)

    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->actingAs($user)->get('/app/my-day', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()) ?? '',
        'X-Inertia-Partial-Component' => 'app/MyDay',
        'X-Inertia-Partial-Data' => 'name',
    ])->assertOk();
    $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn ($q) => str_contains($q, '"notifications"'))->all())->toBe([]);
});
