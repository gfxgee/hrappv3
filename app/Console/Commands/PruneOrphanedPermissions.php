<?php

namespace App\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

#[Signature('permissions:prune-orphaned')]
#[Description('Delete permissions outside the app\'s canonical, role-based set (leftovers from a removed permission generator).')]
class PruneOrphanedPermissions extends Command
{
    /**
     * Delete every permission whose name is not part of the canonical set. The
     * pivot tables cascade on delete, so orphaned rows are detached from roles
     * and users automatically. The permission cache is then cleared.
     */
    public function handle(): int
    {
        $orphans = Permission::query()
            ->whereNotIn('name', RolesAndPermissionsSeeder::PERMISSIONS)
            ->pluck('name', 'id');

        if ($orphans->isEmpty()) {
            $this->info('No orphaned permissions found.');

            return self::SUCCESS;
        }

        Permission::query()->whereKey($orphans->keys())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Pruned {$orphans->count()} orphaned permission(s):");
        $orphans->each(fn (string $name): mixed => $this->line("  - {$name}"));

        return self::SUCCESS;
    }
}
