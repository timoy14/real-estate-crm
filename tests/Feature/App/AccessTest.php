<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest visiting /app/my-day is redirected to /login', function () {
    $this->get('/app/my-day')->assertRedirect('/login');
    $this->get('/app')->assertRedirect('/login');
});

test('agent can open /app/my-day', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)->get('/app/my-day')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('app/MyDay'));
});

test('/app redirects authenticated users to my day', function () {
    $this->actingAs(User::factory()->create())->get('/app')
        ->assertRedirect('/app/my-day');
    expect(route('app.my-day', absolute: false))->toBe('/app/my-day');
});

test('GET /register returns 404', function () {
    $this->get('/register')->assertNotFound();
});

test('POST /register returns 404 or 405 and creates no user', function () {
    $response = $this->post('/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($response->getStatusCode())->toBeIn([404, 405]);
    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('starter kit dashboard route is gone', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertNotFound();
});

test('login redirects to /app/my-day', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/app/my-day');
    $this->assertAuthenticated();
});

test('already authenticated visitor to /login is sent to my day', function () {
    $this->actingAs(User::factory()->create())->get('/login')
        ->assertRedirect('/app/my-day');
});

test('User::isAdmin is true only for the admin role', function () {
    expect(User::factory()->create()->role)->toBe(Role::Agent);
    expect(User::factory()->create()->isAdmin())->toBeFalse();
    expect(User::factory()->create(['role' => Role::Admin])->isAdmin())->toBeTrue();
});

test('role cannot be mass assigned', function () {
    $user = User::factory()->create();
    $user->fill(['role' => 'admin']);
    expect($user->isAdmin())->toBeFalse();
});

test('shared prop auth.user.role is present', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)->get('/app/my-day')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $admin->id)
            ->where('auth.user.role', 'admin')
            ->where('auth.user.is_admin', true)
            ->has('auth.user.email')
            ->missing('auth.user.password')
            ->where('counts.newInquiries', 0)
            ->where('counts.unreadNotifications', 0)
            ->where('meta', null));
});

test('settings pages stay behind auth', function () {
    $this->get('/settings/profile')->assertRedirect('/login');
});

test('every /app route is behind auth', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'app'));

    expect($routes)->not->toBeEmpty();
    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('auth');
    }
});

test('public pages render server side meta tags', function () {
    $this->get('/login')->assertSee('<title', false)->assertSee('Skyline Realty', false);
});

test('meta prop renders title, description and og tags server side', function () {
    Route::get('/_meta-test', fn () => Inertia::render('public/Listings/Index', ['meta' => [
        'title' => '3 bed house',
        'description' => 'Nice <place>',
        'image' => 'https://x.test/a.jpg',
        'url' => 'https://x.test/properties/a',
    ]]));

    $this->get('/_meta-test')
        ->assertSee('<title>3 bed house</title>', false)
        ->assertSee('<meta name="description" content="Nice &lt;place&gt;">', false)
        ->assertSee('<meta property="og:title" content="3 bed house">', false)
        ->assertSee('<meta property="og:image" content="https://x.test/a.jpg">', false)
        ->assertSee('<meta property="og:url" content="https://x.test/properties/a">', false);
});
