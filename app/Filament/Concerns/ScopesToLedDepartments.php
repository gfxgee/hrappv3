<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts a request resource's records to employees a team leader oversees.
 *
 * Full-access roles (superadmin, super_admin, hr) see everything. A team
 * leader only sees requests filed by members of the department(s) they lead.
 * Assumes the resource's model has a `user_id` column.
 */
trait ScopesToLedDepartments
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->hasAnyRole(['superadmin', 'super_admin', 'hr'])) {
            return $query;
        }

        $departmentIds = $user->ledDepartments()->pluck('departments.id');

        return $query->whereIn(
            'user_id',
            User::query()->whereIn('department_id', $departmentIds)->select('id'),
        );
    }
}
