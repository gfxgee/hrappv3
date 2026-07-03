<?php

namespace App\Http\Responses;

use App\Support\MobileAudience;
use Filament\Auth\Http\Responses\LoginResponse as BaseLoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * All logins funnel through the Filament panel login (see FortifyServiceProvider).
 * Send phones and regular employees to the mobile app afterwards instead of the
 * admin panel; everyone else keeps Filament's default panel redirect.
 */
class FilamentLoginResponse extends BaseLoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (MobileAudience::matches($request)) {
            return new RedirectResponse(route('mobile.home'));
        }

        return parent::toResponse($request);
    }
}
