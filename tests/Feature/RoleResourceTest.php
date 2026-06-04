<?php

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    foreach (['superadmin', 'super_admin', 'hr'] as $role) {
        Role::findOrCreate($role);
    }
});

function actingAsSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    test()->actingAs($user);

    return $user;
}

it('lets super-admins access the role resource', function () {
    actingAsSuperAdmin();

    expect(RoleResource::canAccess())->toBeTrue();
});

it('denies non-super-admins access to the role resource', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    expect(RoleResource::canAccess())->toBeFalse();
})->with(['hr']);

it('denies users with no role access to the role resource', function () {
    $this->actingAs(User::factory()->create());

    expect(RoleResource::canAccess())->toBeFalse();
});

it('renders the role list page', function () {
    actingAsSuperAdmin();

    Livewire::test(ListRoles::class)->assertSuccessful();
});

it('creates a role with permissions attached', function () {
    actingAsSuperAdmin();
    $permission = Permission::findOrCreate('manage users');

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'approver',
            'permissions' => [$permission->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::findByName('approver');

    expect($role->guard_name)->toBe('web')
        ->and($role->hasPermissionTo('manage users'))->toBeTrue();
});

it('updates the permissions attached to a role', function () {
    actingAsSuperAdmin();
    $role = Role::findOrCreate('approver');
    $permission = Permission::findOrCreate('manage users');

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm([
            'name' => 'approver',
            'permissions' => [$permission->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->refresh()->hasPermissionTo('manage users'))->toBeTrue();
});

it('hides the delete action for protected super-admin roles', function () {
    actingAsSuperAdmin();
    $protected = Role::findByName('super_admin');
    $custom = Role::findOrCreate('approver');

    Livewire::test(ListRoles::class)
        ->assertTableActionHidden('delete', $protected)
        ->assertTableActionVisible('delete', $custom);
});
