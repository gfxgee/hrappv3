<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Decides whether a request should be served the employee mobile app rather
 * than the desktop experience: true on phones (user-agent) or for regular
 * employees (anyone who isn't a manager or a team leader).
 */
class MobileAudience
{
    public static function matches(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return self::isMobile($request) || (! $user->isManager() && ! $user->isTeamLeader());
    }

    /**
     * A lightweight user-agent check for phones and small tablets.
     */
    public static function isMobile(Request $request): bool
    {
        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini|IEMobile/i',
            (string) $request->userAgent(),
        );
    }
}
