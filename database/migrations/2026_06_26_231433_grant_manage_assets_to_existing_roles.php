<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The Asset module is now gated by the "manage assets" permission rather
     * than a hard-coded role list. Grant it to the roles that previously had
     * access (HR and Office Manager) so existing environments keep working.
     * Super-admins bypass permission checks, so they need no grant.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'manage assets']);

        foreach (['hr', 'office_manager'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['hr', 'office_manager'] as $roleName) {
            Role::where('name', $roleName)->first()?->revokePermissionTo('manage assets');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
