<?php

use App\Filament\Pages\ImportAttendance;
use App\Models\AttendanceLog;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    Role::findOrCreate('hr');
    $this->manager = User::factory()->create();
    $this->manager->assignRole('hr');
});

it('renders for a manager', function () {
    $this->actingAs($this->manager);

    Livewire::test(ImportAttendance::class)->assertSuccessful();
});

it('is not accessible to employees without a manager role', function () {
    $this->actingAs(User::factory()->create());

    expect(ImportAttendance::canAccess())->toBeFalse();
});

it('commits reviewed preview rows into attendance logs', function () {
    $this->actingAs($this->manager);
    $employee = User::factory()->create(['bio_metric_id' => 104]);

    $row = [
        'key' => '104-2025-12-17',
        'bio_metric_id' => 104,
        'employee_name' => $employee->name,
        'user_id' => $employee->id,
        'date' => '2025-12-17',
        'time_in' => '2025-12-17 08:00:00',
        'time_out' => '2025-12-17 17:00:00',
        'punch_count' => 2,
        'status' => 'ok',
    ];

    Livewire::test(ImportAttendance::class)
        ->set('previewRows', ['104-2025-12-17' => $row])
        ->callAction('commit')
        ->assertHasNoActionErrors();

    expect(AttendanceLog::where('user_id', $employee->id)->count())->toBe(2);
});
