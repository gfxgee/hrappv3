<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Decides whether a request should be served the mobile app rather than the
 * desktop experience — purely by device (user-agent). Role never influences
 * which UI a user gets; it only governs what they can do once inside.
 */
class MobileAudience
{
    public static function isMobile(Request $request): bool
    {
        return (bool) preg_match(
            '/Mobile|Android|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini|IEMobile/i',
            (string) $request->userAgent(),
        );
    }
}
