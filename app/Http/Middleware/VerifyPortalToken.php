<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPortalToken
{
    /**
     * Reject the request unless it carries the DF Portal's shared token in the
     * X-Portal-Token header. Uses a timing-safe comparison, and refuses every
     * request while no token is configured.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.portal.token');
        $provided = (string) $request->header('X-Portal-Token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid or missing portal token.');
        }

        return $next($request);
    }
}
