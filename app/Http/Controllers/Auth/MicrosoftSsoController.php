<?php

namespace App\Http\Controllers\Auth;

use App\Enum\UserStatus;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Microsoft / Azure AD single sign-on for the admin panel.
 *
 * Access is restricted to existing, active employees: the Microsoft email
 * must match a user already provisioned by HR. No accounts are auto-created.
 */
class MicrosoftSsoController
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->denied('We could not complete the Microsoft sign-in. Please try again.');
        }

        $email = $microsoftUser->getEmail();

        $user = filled($email)
            ? User::query()->where('email', $email)->first()
            : null;

        if ($user === null || $user->status !== UserStatus::ACTIVE->value) {
            return $this->denied('No active employee account matches that Microsoft account.');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(Filament::getPanel('admin')->getUrl());
    }

    /**
     * Flash a Filament notification and return the user to the panel login.
     */
    protected function denied(string $message): RedirectResponse
    {
        Notification::make()
            ->danger()
            ->title('Sign-in denied')
            ->body($message)
            ->send();

        return redirect()->route('filament.admin.auth.login');
    }
}
