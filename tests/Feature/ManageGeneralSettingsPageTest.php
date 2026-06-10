<?php

use App\Filament\Pages\ManageGeneralSettings;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function settingsManager(string $role): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets manager roles access the settings page', function (string $role) {
    $this->actingAs(settingsManager($role));

    expect(ManageGeneralSettings::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('denies regular users access to the settings page', function () {
    $this->actingAs(User::factory()->create());

    expect(ManageGeneralSettings::canAccess())->toBeFalse();
});

it('renders the settings page for a manager', function () {
    $this->actingAs(settingsManager('hr'));

    Livewire::test(ManageGeneralSettings::class)->assertSuccessful();
});

it('persists changed settings from the form', function () {
    $this->actingAs(settingsManager('hr'));

    Livewire::test(ManageGeneralSettings::class)
        ->fillForm([
            'lunchHours' => 0.5,
            'lunchThresholdHours' => 6.0,
            'standardWorkingHours' => 8.0,
            'workingDays' => [1, 2, 3, 4, 5, 6],
            'birthdayWindowDays' => 30,
            'holidayWindowDays' => 120,
            'leaveWindowDays' => 45,
            'biometricDedupeMinutes' => 5,
            'praiseGifPerPage' => 20,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GeneralSettings::class);

    expect($settings->lunchHours)->toBe(0.5)
        ->and($settings->lunchThresholdHours)->toBe(6.0)
        ->and($settings->workingDays)->toBe([1, 2, 3, 4, 5, 6])
        ->and($settings->holidayWindowDays)->toBe(120)
        ->and($settings->biometricDedupeMinutes)->toBe(5)
        ->and($settings->praiseGifPerPage)->toBe(20);
});
