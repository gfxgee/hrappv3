<?php

namespace App\Http\Middleware;

use App\Support\MobileAudience;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends authenticated visitors on a phone to the mobile app when they land on
 * the Filament admin panel. Purely device-based — role is irrelevant. Guests
 * fall through so the panel login page stays reachable on a phone (otherwise
 * the redirect would loop).
 */
class RedirectMobileToApp
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && MobileAudience::isMobile($request)) {
            return redirect()->route('mobile.home');
        }

        return $next($request);
    }
}
