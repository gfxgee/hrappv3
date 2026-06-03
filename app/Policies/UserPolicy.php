<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Whether the authenticated user may manage employees (create/edit/delete).
     * Users with no assigned roles get read-only access.
     */
    protected function canManage(User $user): bool
    {
        return $user->roles()->exists();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, User $model): bool
    {
        return $this->canManage($user);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->canManage($user);
    }
}
