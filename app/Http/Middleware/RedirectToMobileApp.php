<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends regular employees to the mobile self-service app. HR/admins and team
 * leaders keep the desktop experience (Filament admin + Inertia dashboard),
 * since their work — approvals, management — lives there.
 */
class RedirectToMobileApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isManager() && ! $user->isTeamLeader()) {
            return redirect()->route('mobile.home');
        }

        return $next($request);
    }
}
