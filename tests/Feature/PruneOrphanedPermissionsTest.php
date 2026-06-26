<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('deletes orphaned permissions while keeping the canonical set', function () {
    foreach (RolesAndPermissionsSeeder::PERMISSIONS as $name) {
        Permission::findOrCreate($name);
    }

    Permission::findOrCreate('Create:Badge');
    Permission::findOrCreate('Restore:Role');

    expect(Permission::count())->toBe(count(RolesAndPermissionsSeeder::PERMISSIONS) + 2);

    $this->artisan('permissions:prune-orphaned')->assertSuccessful();

    expect(Permission::whereIn('name', ['Create:Badge', 'Restore:Role'])->count())->toBe(0)
        ->and(Permission::pluck('name')->sort()->values()->all())
        ->toBe(collect(RolesAndPermissionsSeeder::PERMISSIONS)->sort()->values()->all());
});

it('detaches pruned permissions from roles but keeps canonical grants', function () {
    $role = Role::findOrCreate('office_manager');
    $canonical = Permission::findOrCreate('manage assets');
    $orphan = Permission::findOrCreate('Delete:User');
    $role->givePermissionTo([$canonical, $orphan]);

    $this->artisan('permissions:prune-orphaned')->assertSuccessful();

    expect($role->fresh()->hasPermissionTo('manage assets'))->toBeTrue()
        ->and(Permission::where('name', 'Delete:User')->exists())->toBeFalse();
});

it('is a no-op when there are no orphaned permissions', function () {
    foreach (RolesAndPermissionsSeeder::PERMISSIONS as $name) {
        Permission::findOrCreate($name);
    }

    $this->artisan('permissions:prune-orphaned')
        ->expectsOutputToContain('No orphaned permissions found.')
        ->assertSuccessful();

    expect(Permission::count())->toBe(count(RolesAndPermissionsSeeder::PERMISSIONS));
});
