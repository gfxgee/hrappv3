<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proves that a token handed over by the Teams client is genuine, and resolves
 * the company email behind it.
 *
 * The Teams JS SDK returns an Entra ID token addressed to this application.
 * Trusting its claims unverified would let anyone sign in as anyone, so the
 * token is exchanged with Entra using the on-behalf-of flow: Entra validates
 * the signature, issuer, expiry and audience, and refuses any assertion that
 * was not issued for this app's client ID. A successful exchange is therefore
 * the proof, and Microsoft Graph then supplies the authoritative address
 * rather than a self-reported claim.
 *
 * Doing it this way keeps the app free of JWT signing libraries and of the
 * signing-key rotation that local validation would have to track.
 */
class TeamsSsoTokenVerifier
{
    /**
     * The verified primary email for the token's user, or null when the token
     * is not valid for this application.
     */
    public function emailFor(string $token): ?string
    {
        $graphToken = $this->exchange($token);

        if ($graphToken === null) {
            return null;
        }

        return $this->emailFromGraph($graphToken);
    }

    /**
     * Swap the caller's token for a Graph token (on-behalf-of). Entra rejects
     * anything it did not issue for this client, which is the actual check.
     */
    private function exchange(string $token): ?string
    {
        $clientId = (string) config('services.microsoft.client_id');
        $clientSecret = (string) config('services.microsoft.client_secret');
        $tenant = (string) config('services.microsoft.tenant', 'common');

        if ($clientId === '' || $clientSecret === '') {
            Log::warning('Teams SSO attempted without Microsoft credentials configured.');

            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'assertion' => $token,
                    'scope' => 'https://graph.microsoft.com/User.Read',
                    'requested_token_use' => 'on_behalf_of',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($response->failed()) {
            // Never log the token or the response body: both carry user
            // identifiers, and a failure here is routine (expired token,
            // consent not yet granted).
            Log::warning('Teams SSO token exchange rejected.', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return null;
        }

        $accessToken = $response->json('access_token');

        return is_string($accessToken) && $accessToken !== '' ? $accessToken : null;
    }

    /**
     * Read the signed-in user's address from Graph. `mail` is the real mailbox
     * address; userPrincipalName is the fallback for accounts without one.
     */
    private function emailFromGraph(string $graphToken): ?string
    {
        try {
            $response = Http::withToken($graphToken)
                ->acceptJson()
                ->timeout(10)
                ->get('https://graph.microsoft.com/v1.0/me', [
                    '$select' => 'mail,userPrincipalName',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Teams SSO could not read the profile from Graph.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $email = $response->json('mail') ?: $response->json('userPrincipalName');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
