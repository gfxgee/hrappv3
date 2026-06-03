<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesAndPermissionsSeeder extends Seeder
{
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

        // 👑 Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superAdmin2 = Role::firstOrCreate(['name' => 'super_admin']);
        $hr = Role::firstOrCreate(['name' => 'hr']);
        $teamLeader = Role::firstOrCreate(['name' => 'teamleader']);

        // 🔗 Assign permissions to roles
        $superAdmin->givePermissionTo(Permission::all());
        $superAdmin2->givePermissionTo(Permission::all());
        $hr->givePermissionTo('manage users');
        $teamLeader->givePermissionTo('manage own team');

        // ✅ You can also assign a role to a user here if needed
        // $user = \App\Models\User::find(1);
        // $user->assignRole('SUPER ADMIN');
    }
}
