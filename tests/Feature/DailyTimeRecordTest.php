<?php

use App\Enum\AttendanceStatus;
use App\Filament\Pages\DailyTimeRecord;
use App\Models\OverTimeRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('renders the daily time record page', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(DailyTimeRecord::class)
        ->assertSuccessful()
        ->assertSee('Daily Time Record');
});

it('locks a regular employee to their own record', function () {
    $me = User::factory()->create(['status' => 'active']);
    $other = User::factory()->create(['status' => 'active']);
    $this->actingAs($me);

    $page = Livewire::test(DailyTimeRecord::class);

    expect($page->instance()->employeeOptions())->toHaveCount(1)
        ->and($page->instance()->canSelectEmployee())->toBeFalse();

    // Even if a different id is forced, it resolves back to the current user.
    $page->set('employeeId', (string) $other->id);
    expect($page->instance()->resolveEmployee()->id)->toBe($me->id);
});

it('lets a manager view any employee', function () {
    Role::findOrCreate('hr');
    $hr = User::factory()->create(['status' => 'active']);
    $hr->assignRole('hr');
    $employee = User::factory()->create(['status' => 'active']);
    $this->actingAs($hr);

    $page = Livewire::test(DailyTimeRecord::class)->set('employeeId', (string) $employee->id);

    expect($page->instance()->canSelectEmployee())->toBeTrue()
        ->and($page->instance()->resolveEmployee()->id)->toBe($employee->id);
});

it('switches the period with the month shortcuts', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(DailyTimeRecord::class)
        ->call('lastMonth')
        ->assertSet('from', now()->subMonthNoOverflow()->startOfMonth()->toDateString())
        ->assertSet('until', now()->subMonthNoOverflow()->endOfMonth()->toDateString());
});

it('formats minutes as a human-readable duration', function () {
    $page = new DailyTimeRecord;

    expect($page->humanMinutes(590))->toBe('9h 50m')
        ->and($page->humanMinutes(80))->toBe('1h 20m')
        ->and($page->humanMinutes(375))->toBe('6h 15m')
        ->and($page->humanMinutes(45))->toBe('45m')
        ->and($page->humanMinutes(120))->toBe('2h');
});

it('exports the DTR as a CSV download', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(DailyTimeRecord::class)
        ->call('exportCsv')
        ->assertFileDownloaded();
});

it('includes the overtime breakdown in the CSV export', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'request_date' => now()->startOfMonth()->addDays(2),
        'hours' => 1.5,
    ]);
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => now()->startOfMonth()->addDays(2),
        'hours' => 4.0,
    ]);

    $component = Livewire::test(DailyTimeRecord::class)
        ->call('exportCsv')
        ->assertFileDownloaded();

    $csv = base64_decode((string) data_get(
        (fn () => $this->effects)->call($component),
        'download.content',
    ));

    expect($csv)->toContain('OT Breakdown')
        ->toContain('1.5 For Approval')
        ->toContain('4 Approved')
        ->toContain('Pending: 1.5');
});
