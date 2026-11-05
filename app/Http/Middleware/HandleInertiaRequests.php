<?php

namespace App\Http\Middleware;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'ai_enabled' => (bool) config('crm.ai.enabled'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'role' => $user->role->value,
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],
            'counts' => [
                // Closures run on every full visit; only partial reloads that skip `counts` avoid the queries.
                'newInquiries' => fn () => $user
                    ? Inquiry::query()->visibleTo($user)->where('status', InquiryStatus::New->value)->count()
                    : 0,
                'unreadNotifications' => fn () => $user?->unreadNotifications()->count() ?? 0,
            ],
            // Pages set this (Inertia::share / render prop) for server-side <head> tags.
            'meta' => null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Lets route() resolve named routes during SSR, where the @routes global doesn't exist.
            'ziggy' => Inertia::once(fn () => (new Ziggy)->toArray()),
        ];
    }
}
