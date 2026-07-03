<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the unread-notification count with every mobile screen so the bottom
 * navigation badge stays in sync without each controller having to provide it.
 */
class ShareMobileBadge
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('unread', fn (): int => $request->user()?->unreadNotifications()->count() ?? 0);

        return $next($request);
    }
}
