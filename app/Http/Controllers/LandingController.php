<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LandingController extends Controller
{
    /**
     * The site's front door. Guests see the welcome page; signed-in users are
     * sent straight to their app — the mobile self-service app on phones (or
     * for regular employees), the desktop dashboard otherwise.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return Inertia::render('welcome');
        }

        if ($this->isMobile($request) || (! $user->isManager() && ! $user->isTeamLeader())) {
            return redirect()->route('mobile.home');
        }

        return redirect()->route('dashboard');
    }

    /**
     * A lightweight user-agent check for phones and small tablets.
     */
    private function isMobile(Request $request): bool
    {
        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini|IEMobile/i',
            (string) $request->userAgent(),
        );
    }
}
