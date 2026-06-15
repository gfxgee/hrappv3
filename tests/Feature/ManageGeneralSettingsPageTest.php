<?php

use App\Filament\Pages\ManageGeneralSettings;
use App\Filament\Widgets\Employee\ComingUpWidget;
use App\Models\Holiday;
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

it('is reached from the user menu, not the sidebar', function () {
    $this->actingAs(settingsManager('hr'));

    expect(ManageGeneralSettings::shouldRegisterNavigation())->toBeFalse();

    $items = Filament::getPanel('admin')->getUserMenuItems();

    expect($items)->toHaveKey('settings')
        ->and($items['settings']->getUrl())->toBe(ManageGeneralSettings::getUrl())
        ->and($items['settings']->isVisible())->toBeTrue();
});

it('hides the settings user-menu item from non-managers', function () {
    $this->actingAs(User::factory()->create());

    $items = Filament::getPanel('admin')->getUserMenuItems();

    expect($items)->not->toHaveKey('settings');
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
            'comingUpWindowDays' => 30,
            'biometricDedupeMinutes' => 5,
            'praiseGifPerPage' => 20,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GeneralSettings::class);

    expect($settings->lunchHours)->toBe(0.5)
        ->and($settings->lunchThresholdHours)->toBe(6.0)
        ->and($settings->workingDays)->toBe([1, 2, 3, 4, 5, 6])
        ->and($settings->comingUpWindowDays)->toBe(30)
        ->and($settings->biometricDedupeMinutes)->toBe(5)
        ->and($settings->praiseGifPerPage)->toBe(20);
});

it('drives the coming up widget window from the setting', function () {
    $this->actingAs(settingsManager('hr'));

    GeneralSettings::fake(['comingUpWindowDays' => 5]);

    Holiday::create(['name' => 'Inside Window', 'date' => today()->addDays(4)->toDateString()]);
    Holiday::create(['name' => 'Outside Window', 'date' => today()->addDays(10)->toDateString()]);

    $widget = Livewire::test(ComingUpWidget::class)->instance();
    $labels = $widget->entries()->pluck('label');

    expect($widget->windowDays)->toBe(5)
        ->and($labels)->toContain('Inside Window')
        ->and($labels)->not->toContain('Outside Window');
});
