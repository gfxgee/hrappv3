<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The app's canonical permissions. Authorization is role-based, so this
     * set is deliberately small; anything else in the permissions table is an
     * orphan (see the permissions:prune-orphaned command).
     *
     * @var list<string>
     */
    public const PERMISSIONS = ['manage all', 'manage users', 'manage own team', 'manage assets'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 🧾 Define permissions
        Permission::firstOrCreate(['name' => 'manage all']); // SUPER ADMIN
        Permission::firstOrCreate(['name' => 'manage users']); // HR
        Permission::firstOrCreate(['name' => 'manage own team']); // TEAM LEADER
        Permission::firstOrCreate(['name' => 'manage assets']); // OFFICE MANAGER

        // 👑 Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superAdmin2 = Role::firstOrCreate(['name' => 'super_admin']);
        $hr = Role::firstOrCreate(['name' => 'hr']);
        $teamLeader = Role::firstOrCreate(['name' => 'teamleader']);
        $officeManager = Role::firstOrCreate(['name' => 'office_manager']);

        // 🔗 Assign permissions to roles
        $superAdmin->givePermissionTo(Permission::all());
        $superAdmin2->givePermissionTo(Permission::all());
        $hr->givePermissionTo('manage users');
        $teamLeader->givePermissionTo('manage own team');
        $officeManager->givePermissionTo('manage assets');

        // ✅ You can also assign a role to a user here if needed
        // $user = \App\Models\User::find(1);
        // $user->assignRole('SUPER ADMIN');
    }
}
