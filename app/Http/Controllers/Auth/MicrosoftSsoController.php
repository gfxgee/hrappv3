<?php

namespace App\Http\Controllers\Auth;

use App\Support\SsoEmployee;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Microsoft / Azure AD single sign-on for the admin panel.
 *
 * Access is restricted to existing, active employees: the Microsoft email
 * must match a user already provisioned by HR. No accounts are auto-created.
 *
 * The same endpoints serve the Microsoft Teams tab, which opens them in a
 * popup when silent sign-in is unavailable. That case cannot simply log the
 * user in here: the popup is a top-level window, so its session cookie lands
 * in a different jar from the partitioned one the embedded tab uses. Instead
 * the popup hands a single-use code back to the tab, which redeems it.
 */
class MicrosoftSsoController
{
    /**
     * Carries the tab's nonce across the Microsoft round trip, inside the
     * popup's own session.
     */
    private const TEAMS_NONCE_SESSION_KEY = 'sso_teams_nonce';

    public function redirect(Request $request): RedirectResponse
    {
        $nonce = $request->string('teams_nonce')->toString();

        if ($nonce !== '') {
            $request->session()->put(self::TEAMS_NONCE_SESSION_KEY, $nonce);
        } else {
            $request->session()->forget(self::TEAMS_NONCE_SESSION_KEY);
        }

        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(Request $request): RedirectResponse|View
    {
        $teamsNonce = $request->session()->pull(self::TEAMS_NONCE_SESSION_KEY);

        try {
            $microsoftUser = Socialite::driver('microsoft')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->denied('We could not complete the Microsoft sign-in. Please try again.', $teamsNonce);
        }

        $user = SsoEmployee::resolve($microsoftUser->getEmail());

        if ($user === null) {
            return $this->denied('No active employee account matches that Microsoft account.', $teamsNonce);
        }

        if (filled($teamsNonce)) {
            return view('teams.popup', [
                'code' => TeamsSsoController::issueClaim($user, $teamsNonce),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(Filament::getPanel('admin')->getUrl());
    }

    /**
     * Report the refusal the way the caller can act on it: a Teams popup
     * closes itself with a message, a browser returns to the panel login.
     */
    protected function denied(string $message, ?string $teamsNonce = null): RedirectResponse|View
    {
        if (filled($teamsNonce)) {
            return view('teams.popup', ['error' => $message]);
        }

        Notification::make()
            ->danger()
            ->title('Sign-in denied')
            ->body($message)
            ->send();

        return redirect()->route('filament.admin.auth.login');
    }
}
