<?php

use App\Filament\Resources\OnCallMembers\OnCallMemberResource;
use App\Filament\Resources\OnCallMembers\Pages\EditOnCallMember;
use App\Filament\Resources\OnCallMembers\Pages\ListOnCallMembers;
use App\Models\OnCallMember;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function onCallManager(string $role = 'hr'): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

it('lets managers access the on-call rotation resource', function () {
    $this->actingAs(onCallManager('hr'));

    expect(OnCallMemberResource::canAccess())->toBeTrue();
});

it('denies regular users access to the on-call rotation resource', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    expect(OnCallMemberResource::canAccess())->toBeFalse();
});

it('renders the roster list with the current on-call in the subheading', function () {
    $this->actingAs(onCallManager('hr'));

    $dev = User::factory()->create(['name' => 'Roster Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $dev->id, 'position' => 1]);

    Livewire::test(ListOnCallMembers::class)
        ->assertSuccessful()
        ->assertSee('Roster Dev')
        // The roster shows each dev's next on-call week; the sole member is on now.
        ->assertSee('This week');
});

it('shows the developer name (not the id) when editing a roster member', function () {
    $this->actingAs(onCallManager('hr'));

    $dev = User::factory()->create(['name' => 'Editable Dev', 'status' => 'active']);
    $member = OnCallMember::create(['user_id' => $dev->id, 'position' => 1]);

    Livewire::test(EditOnCallMember::class, ['record' => $member->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Editable Dev');
});
