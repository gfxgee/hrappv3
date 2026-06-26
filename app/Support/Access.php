<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Role-based access to gated panel "areas". Each area maps to a permission in
 * config/access.php; a role granted that permission (in the Roles UI) unlocks
 * the area without any code change. Super-admins always pass.
 */
class Access
{
    /**
     * The permission that unlocks the given area, or null if the area is
     * unknown / not gated.
     */
    public static function permissionFor(string $area): ?string
    {
        return config("access.areas.{$area}");
    }

    /**
     * Whether the signed-in user may access the given area.
     */
    public static function allows(string $area): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::permissionFor($area);

        return $permission !== null && $user->can($permission);
    }
}
