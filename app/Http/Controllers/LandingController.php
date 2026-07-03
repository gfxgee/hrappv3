<?php

namespace App\Http\Controllers;

use App\Support\MobileAudience;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LandingController extends Controller
{
    /**
     * The site's front door. Guests see the welcome page; signed-in users go
     * straight to their app — the mobile self-service app on phones, the
     * Filament admin panel on desktop.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() === null) {
            return Inertia::render('welcome');
        }

        if (MobileAudience::isMobile($request)) {
            return redirect()->route('mobile.home');
        }

        return redirect('/admin');
    }
}
