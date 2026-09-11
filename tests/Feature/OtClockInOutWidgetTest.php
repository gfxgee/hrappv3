<?php

use App\Enum\AttendanceStatus;
use App\Filament\Widgets\ClockInOutWidget;
use App\Models\AttendanceLog;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Services\DtrService;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

/**
 * Clock in for overtime through the modal action.
 */
function otClockIn(float $hours = 2.0, string $reason = 'Release deployment'): Testable
{
    return Livewire::test(ClockInOutWidget::class)
        ->callAction('otClockIn', data: ['hours' => $hours, 'reason' => $reason]);
}

it('files the overtime request and clocks in, in one step', function () {
    otClockIn(hours: 2.5, reason: 'Client demo prep')->assertHasNoActionErrors();

    $request = OverTimeRequest::query()->sole();
    $log = AttendanceLog::query()->sole();

    expect($request->user_id)->toBe(auth()->id())
        ->and($request->status)->toBe(AttendanceStatus::FOR_APPROVAL)
        ->and($request->hours)->toBe(2.5)
        ->and($request->reason)->toBe('Client demo prep')
        ->and($request->request_date->toDateString())->toBe(today()->toDateString())
        // The punch is an ordinary clock-in — the shift behaves normally from here.
        ->and($log->type)->toBe('clockin')
        ->and($log->device)->toBe('web')
        ->and($log->remarks)->toContain("#{$request->id}");
});

it('puts the shift in progress just like a normal clock-in', function () {
    otClockIn();

    $widget = new ClockInOutWidget;

    expect($widget->getStatus())->toBe('in_progress')
        ->and($widget->hasClockedInToday())->toBeTrue()
        ->and($widget->canClockIn())->toBeFalse()
        ->and($widget->isOvertimeShift())->toBeTrue();
});

it('clocks out of an overtime shift with the normal clock-out', function () {
    otClockIn();

    Livewire::test(ClockInOutWidget::class)->call('clockOut');

    expect(AttendanceLog::where('type', 'clockout')->count())->toBe(1)
        ->and((new ClockInOutWidget)->getStatus())->toBe('done');
});

it('does not OT clock in when already clocked in', function () {
    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    otClockIn();

    expect(AttendanceLog::where('type', 'clockin')->count())->toBe(1)
        ->and(OverTimeRequest::query()->count())->toBe(0);
});

it('does not OT clock in twice', function () {
    otClockIn();
    otClockIn();

    expect(AttendanceLog::where('type', 'clockin')->count())->toBe(1)
        ->and(OverTimeRequest::query()->count())->toBe(1);
});

it('does not file an overtime request for a normal clock-in', function () {
    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    expect(OverTimeRequest::query()->count())->toBe(0)
        ->and((new ClockInOutWidget)->isOvertimeShift())->toBeFalse();
});

it('validates the filed hours against the configured maximum', function () {
    GeneralSettings::fake(['maxOvertimeHours' => 4.0]);

    otClockIn(hours: 5.0)->assertHasActionErrors(['hours']);
    otClockIn(hours: 0.25)->assertHasActionErrors(['hours']);

    // Nothing is filed and, crucially, the employee is not clocked in either.
    expect(OverTimeRequest::query()->count())->toBe(0)
        ->and(AttendanceLog::query()->count())->toBe(0);
});

it('requires a reason', function () {
    otClockIn(reason: '')->assertHasActionErrors(['reason']);

    expect(OverTimeRequest::query()->count())->toBe(0)
        ->and(AttendanceLog::query()->count())->toBe(0);
});

it('ignores a request_date supplied by the client', function () {
    // The modal has no date field — the date is the day you clocked in.
    Livewire::test(ClockInOutWidget::class)
        ->callAction('otClockIn', data: [
            'hours' => 2.0,
            'reason' => 'Legit overtime',
            'request_date' => today()->addMonth()->toDateString(),
        ]);

    expect(OverTimeRequest::query()->sole()->request_date->toDateString())
        ->toBe(today()->toDateString());
});

it('offers both clock-in buttons before the shift starts', function () {
    Livewire::test(ClockInOutWidget::class)
        ->assertSuccessful()
        ->assertActionExists('otClockIn')
        ->assertSee('OT Clock In')
        ->assertSee('Clock In');
});

it('offers both clock-out buttons while the shift is open', function () {
    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    Livewire::test(ClockInOutWidget::class)
        ->assertSuccessful()
        ->assertActionExists('otClockOut')
        ->assertSee('OT Clock Out')
        ->assertSee('Clock Out');
});

it('marks an OT-started shift in the subtitle', function () {
    otClockIn();

    Livewire::test(ClockInOutWidget::class)
        ->assertSuccessful()
        ->assertSee('Overtime shift in progress');
});

it('counts the overtime shift on the DTR without changing worked hours', function () {
    $user = auth()->user();
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    otClockIn(hours: 2.0);
    Livewire::test(ClockInOutWidget::class)->call('clockOut');

    $row = app(DtrService::class)
        ->build($user, today(), today())['rows'][0];

    // One ordinary shift, plus the filed (still pending) overtime.
    expect($row['status'])->toBe('Present')
        ->and($row['overtime'])->toBe(0.0)
        ->and($row['overtime_breakdown'])->toHaveCount(1)
        ->and($row['overtime_breakdown'][0]['hours'])->toBe(2.0)
        ->and($row['overtime_breakdown'][0]['status'])->toBe(AttendanceStatus::FOR_APPROVAL);
});

/**
 * Clock out with overtime through the modal action.
 */
function otClockOut(float $hours = 2.0, string $reason = 'Stayed late for the release'): Testable
{
    return Livewire::test(ClockInOutWidget::class)
        ->callAction('otClockOut', data: ['hours' => $hours, 'reason' => $reason]);
}

it('files the overtime request and clocks out, in one step', function () {
    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    otClockOut(hours: 1.5, reason: 'Hotfix deploy')->assertHasNoActionErrors();

    $request = OverTimeRequest::query()->sole();
    $log = AttendanceLog::query()->where('type', 'clockout')->sole();

    expect($request->status)->toBe(AttendanceStatus::FOR_APPROVAL)
        ->and($request->hours)->toBe(1.5)
        ->and($request->reason)->toBe('Hotfix deploy')
        ->and($request->request_date->toDateString())->toBe(today()->toDateString())
        ->and($log->device)->toBe('web')
        ->and($log->remarks)->toContain("#{$request->id}")
        ->and((new ClockInOutWidget)->getStatus())->toBe('done');
});

it('cannot OT clock out without an open shift', function () {
    otClockOut();

    expect(AttendanceLog::query()->count())->toBe(0)
        ->and(OverTimeRequest::query()->count())->toBe(0);
});

it('does not OT clock out twice', function () {
    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    otClockOut();
    otClockOut();

    expect(AttendanceLog::where('type', 'clockout')->count())->toBe(1)
        ->and(OverTimeRequest::query()->count())->toBe(1);
});

it('does not OT clock out after a normal clock-out', function () {
    Livewire::test(ClockInOutWidget::class)
        ->call('clockIn')
        ->call('clockOut');

    otClockOut();

    expect(AttendanceLog::where('type', 'clockout')->count())->toBe(1)
        ->and(OverTimeRequest::query()->count())->toBe(0);
});

it('files overtime against the shift start date when clocking out after midnight', function () {
    // Night shift: clocked in yesterday evening, clocking out at 2am today.
    $in = AttendanceLog::create(['user_id' => auth()->id(), 'type' => 'clockin', 'device' => 'web']);
    $in->forceFill(['created_at' => now()->subDay()->setTime(20, 0)])->save();

    otClockOut()->assertHasNoActionErrors();

    expect(OverTimeRequest::query()->sole()->request_date->toDateString())
        ->toBe(today()->subDay()->toDateString());
});

it('leaves the employee clocked in when the OT clock-out form is invalid', function () {
    GeneralSettings::fake(['maxOvertimeHours' => 4.0]);
    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    otClockOut(hours: 9.0)->assertHasActionErrors(['hours']);
    otClockOut(reason: '')->assertHasActionErrors(['reason']);

    expect(OverTimeRequest::query()->count())->toBe(0)
        ->and(AttendanceLog::where('type', 'clockout')->count())->toBe(0)
        ->and((new ClockInOutWidget)->getStatus())->toBe('in_progress');
});

it('records the overtime on the DTR when filed at clock-out', function () {
    $user = auth()->user();
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    Livewire::test(ClockInOutWidget::class)->call('clockIn');
    otClockOut(hours: 2.0);

    $row = app(DtrService::class)->build($user, today(), today())['rows'][0];

    expect($row['status'])->toBe('Present')
        ->and($row['overtime_breakdown'])->toHaveCount(1)
        ->and($row['overtime_breakdown'][0]['hours'])->toBe(2.0);
});
