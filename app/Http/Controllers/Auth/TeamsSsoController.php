<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\TeamsSsoTokenVerifier;
use App\Support\MobileAudience;
use App\Support\SsoEmployee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Signs employees in automatically inside the Microsoft Teams tab.
 *
 * Teams hands the page a token for whoever is signed in to Teams, so no one
 * types anything. When that silent path is unavailable — the tenant has not
 * consented yet, or the host client does not support it — the page falls back
 * to the ordinary Microsoft popup, which is why `claim()` exists.
 */
class TeamsSsoController
{
    /**
     * Key under which the tab's one-time nonce lives in its own session.
     */
    public const NONCE_KEY = 'teams_sso_nonce';

    private const CLAIM_PREFIX = 'teams-sso-claim:';

    private const CLAIM_TTL_SECONDS = 120;

    /**
     * The tab's entry point. Loads the Teams SDK, which then calls `token()`
     * or, failing that, the popup flow.
     */
    public function entry(Request $request): View
    {
        // Binds a popup-issued claim code back to this specific tab, so a
        // leaked code cannot be redeemed from anywhere else.
        $nonce = Str::random(40);
        $request->session()->put(self::NONCE_KEY, $nonce);

        return view('teams.app', [
            'nonce' => $nonce,
            'alreadySignedIn' => $request->user() !== null,
            'destination' => $this->destinationFor($request),
        ]);
    }

    /**
     * Silent path: exchange the Teams token for a session.
     */
    public function token(Request $request, TeamsSsoTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $email = $verifier->emailFor($data['token']);

        if ($email === null) {
            return response()->json([
                'message' => 'We could not verify your Microsoft account.',
                // Tells the page to try the popup: an unverified token is
                // usually missing consent rather than genuinely invalid.
                'retry' => 'popup',
            ], 401);
        }

        return $this->signIn($request, $email);
    }

    /**
     * Popup path: redeem the single-use code the popup produced.
     *
     * The session is created here, inside the tab, rather than in the popup —
     * the popup is a top-level window and its cookies land in a different jar
     * from the partitioned one the embedded tab uses.
     */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $claim = Cache::pull(self::CLAIM_PREFIX.hash('sha256', $data['code']));

        if (! is_array($claim) || ! hash_equals(
            (string) $request->session()->get(self::NONCE_KEY),
            (string) ($claim['nonce'] ?? ''),
        )) {
            return response()->json(['message' => 'That sign-in could not be completed.'], 401);
        }

        $user = User::query()->find($claim['user_id'] ?? null);

        if ($user === null) {
            return response()->json(['message' => 'That sign-in could not be completed.'], 401);
        }

        return $this->signIn($request, $user->email);
    }

    /**
     * Hand a verified popup sign-in back to the tab as a single-use code.
     * Called by the Microsoft callback when the popup was opened from Teams.
     */
    public static function issueClaim(User $user, string $nonce): string
    {
        $code = Str::random(64);

        Cache::put(
            self::CLAIM_PREFIX.hash('sha256', $code),
            ['user_id' => $user->getKey(), 'nonce' => $nonce],
            self::CLAIM_TTL_SECONDS,
        );

        return $code;
    }

    /**
     * @return JsonResponse The destination the tab should navigate to.
     */
    private function signIn(Request $request, string $email): JsonResponse
    {
        $user = SsoEmployee::resolve($email);

        if ($user === null) {
            return response()->json([
                'message' => 'No active employee account matches that Microsoft account.',
            ], 403);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['redirect' => $this->destinationFor($request)]);
    }

    /**
     * Phones get the mobile app, the same split the rest of the app uses.
     *
     * Desktop lands on /admin. The Teams panel at /teams-app is built and
     * reachable, but it is not what the tab opens yet: the redesign behind it
     * has not been signed off, and the tab is what everyone sees. Point this
     * back at /teams-app — and re-register KeepTeamsTabInItsPanel on the
     * admin panel — to turn it on.
     */
    private function destinationFor(Request $request): string
    {
        return MobileAudience::isMobile($request)
            ? route('mobile.home')
            : url('/admin');
    }
}
