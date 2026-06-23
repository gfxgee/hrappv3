<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBiometricWebhookSecret
{
    /**
     * Reject the request unless it carries the configured shared secret in the
     * X-Webhook-Secret header. Uses a timing-safe comparison.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.biometric_webhook.secret');
        $provided = (string) $request->header('X-Webhook-Secret', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid or missing webhook secret.');
        }

        return $next($request);
    }
}
