<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function superAdminActor(): User
{
    Role::findOrCreate('superadmin');
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('superadmin');

    return $user;
}

it('shows the impersonate button on the user view page for an eligible target', function () {
    $this->actingAs(superAdminActor());
    $employee = User::factory()->create(['status' => 'active']);

    Livewire::test(ViewUser::class, ['record' => $employee->getRouteKey()])
        ->assertActionVisible('impersonate');
});

it('shows the impersonate button on the user edit page for an eligible target', function () {
    $this->actingAs(superAdminActor());
    $employee = User::factory()->create(['status' => 'active']);

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->assertActionVisible('impersonate');
});

it('hides the impersonate button when viewing another super admin', function () {
    $this->actingAs(superAdminActor());

    Role::findOrCreate('super_admin');
    $otherAdmin = User::factory()->create(['status' => 'active']);
    $otherAdmin->assignRole('super_admin');

    Livewire::test(ViewUser::class, ['record' => $otherAdmin->getRouteKey()])
        ->assertActionHidden('impersonate');
});

it('hides the impersonate button when viewing yourself', function () {
    $admin = superAdminActor();
    $this->actingAs($admin);

    Livewire::test(ViewUser::class, ['record' => $admin->getRouteKey()])
        ->assertActionHidden('impersonate');
});
