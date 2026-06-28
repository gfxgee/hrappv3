<?php

use App\Models\User;
use Filament\Facades\Filament;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('allows only super admins to reach the backups page', function () {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('hr');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);
    expect(Backups::canAccess())->toBeTrue();

    $hr = User::factory()->create();
    $hr->assignRole('hr');
    $this->actingAs($hr);
    expect(Backups::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->create());
    expect(Backups::canAccess())->toBeFalse();
});

it('is configured to back up the database only (no application files)', function () {
    expect(config('backup.backup.source.files.include'))->toBe([]);
});
