<?php

namespace App\Http\Responses;

use App\Support\MobileAudience;
use Filament\Auth\Http\Responses\LoginResponse as BaseLoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * All logins funnel through the Filament panel login (see FortifyServiceProvider).
 * Send phones to the mobile app afterwards; desktop keeps Filament's default
 * panel redirect. Device-based only — role never decides the destination.
 */
class FilamentLoginResponse extends BaseLoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (MobileAudience::isMobile($request)) {
            return new RedirectResponse(route('mobile.home'));
        }

        return parent::toResponse($request);
    }
}
