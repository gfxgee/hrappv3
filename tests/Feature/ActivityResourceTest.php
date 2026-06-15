<?php

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Holiday;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function activityViewer(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets super admins view the activity log', function (string $role) {
    $this->actingAs(activityViewer($role));

    expect(ActivityResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin']);

it('hides the activity log from hr and regular users', function () {
    $this->actingAs(activityViewer('hr'));
    expect(ActivityResource::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->create());
    expect(ActivityResource::canAccess())->toBeFalse();
});

it('is strictly read-only', function () {
    expect(ActivityResource::canCreate())->toBeFalse()
        ->and(ActivityResource::canEdit(new Holiday))->toBeFalse()
        ->and(ActivityResource::canDelete(new Holiday))->toBeFalse()
        ->and(ActivityResource::canDeleteAny())->toBeFalse();
});

it('renders the activity log list with recorded entries', function () {
    $this->actingAs(activityViewer('superadmin'));

    // Generate an audited change — the table shows the subject ("Holiday #..").
    Holiday::create(['name' => 'Charter Day', 'date' => '2026-06-15']);

    Livewire::test(ListActivities::class)
        ->assertSuccessful()
        ->assertSee('Holiday');
});
