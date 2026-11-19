<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TeamsEmbed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TeamsEmbed reads its marker cookie before cookies are decrypted.
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', TeamsEmbed::MARKER]);

        // The biometric scanner POSTs scans without a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'iclock/*',
        ]);

        // Must run before StartSession: inside a Microsoft Teams tab the app
        // is a cross-site iframe, so the session cookie needs different
        // attributes to be sent at all.
        $middleware->web(prepend: [
            TeamsEmbed::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        Integration::handles($exceptions);
    })->create();
