<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/app/my-day');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Checked inside the web group (after the session starts) so the
        // PostTooLargeException render below can flash a form error.
        $middleware->remove(ValidatePostSize::class);

        $middleware->web(append: [
            ValidatePostSize::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Photo uploads over post_max_size: back to the form instead of a 413 page.
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => $request->expectsJson() ? null : back()
            ->withErrors(['photos' => 'Those photos are too large to upload at once. Try fewer or smaller files.']));
    })->create();
