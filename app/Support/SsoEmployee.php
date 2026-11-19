<?php

namespace App\Support;

use App\Enum\UserStatus;
use App\Models\User;

/**
 * Resolves a single sign-on identity to an employee account.
 *
 * Shared by the browser redirect flow and the Teams tab so both answer the
 * same question the same way: SSO authenticates, it never provisions. The
 * address must already belong to an active employee that HR created, so
 * somebody who merely holds a company mailbox cannot get in, and a departed
 * employee loses access the moment their status changes.
 */
class SsoEmployee
{
    public static function resolve(?string $email): ?User
    {
        if (blank($email)) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== UserStatus::ACTIVE->value) {
            return null;
        }

        return $user;
    }
}
