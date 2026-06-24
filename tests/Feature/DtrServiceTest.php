<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Services\DtrService;
use App\Settings\GeneralSettings;
use Illuminate\Support\Carbon;

function logAt(User $user, string $type, string $at): void
{
    $log = AttendanceLog::create(['user_id' => $user->id, 'type' => $type, 'device' => 'web']);
    $log->created_at = $at;
    $log->save();
}

it('computes worked hours, late, and present status for a worked day', function () {
    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    logAt($user, 'clockin', '2026-06-01 09:30:00');  // 30 mins late
    logAt($user, 'clockout', '2026-06-01 18:00:00');

    $data = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'));
    $row = $data['rows'][0];

    expect($row['status'])->toBe('Present')
        ->and($row['hours'])->toBe(7.5)   // 8.5h gross − 1h lunch
        ->and($row['late'])->toBe(30)
        ->and($row['undertime'])->toBe(0)
        ->and($data['totals']['present'])->toBe(1)
        ->and($data['totals']['hours'])->toBe(7.5);
});

it('uses the configured lunch settings when computing hours', function () {
    // No lunch deduction at all.
    GeneralSettings::fake(['lunchHours' => 0.0, 'lunchThresholdHours' => 5.0]);

    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    logAt($user, 'clockin', '2026-06-01 09:00:00');
    logAt($user, 'clockout', '2026-06-01 18:00:00'); // 9h gross span

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['hours'])->toBe(9.0); // full span, no lunch removed
});

it('deducts a configurable half-hour lunch above the threshold', function () {
    GeneralSettings::fake(['lunchHours' => 0.5, 'lunchThresholdHours' => 5.0]);

    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    logAt($user, 'clockin', '2026-06-01 09:00:00');
    logAt($user, 'clockout', '2026-06-01 18:00:00'); // 9h gross

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['hours'])->toBe(8.5); // 9h − 0.5h lunch
});

it('records undertime when clocking out early', function () {
    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '09:00', 'time_out' => '18:00']);

    logAt($user, 'clockin', '2026-06-01 09:00:00');
    logAt($user, 'clockout', '2026-06-01 16:00:00'); // 2h early

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['undertime'])->toBe(120)
        ->and($row['late'])->toBe(0);
});

it('marks a working day with no logs as absent', function () {
    $user = User::factory()->create();

    $data = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01')); // Monday

    expect($data['rows'][0]['status'])->toBe('Absent')
        ->and($data['totals']['absent'])->toBe(1);
});

it('marks holidays and weekend rest days', function () {
    $user = User::factory()->create();
    Holiday::create(['name' => 'Holiday', 'date' => '2026-06-01']);

    $rows = collect(app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-06'))['rows'])
        ->keyBy(fn (array $row): string => $row['date']->toDateString());

    expect($rows['2026-06-01']['status'])->toBe('Holiday')   // Monday holiday
        ->and($rows['2026-06-06']['status'])->toBe('Rest day'); // Saturday
});

it('marks approved leave days', function () {
    $user = User::factory()->create();
    LeaveRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_type' => LeaveType::VACATION,
        'start_date' => '2026-06-02',
        'end_date' => '2026-06-02',
    ]);

    $data = app(DtrService::class)->build($user, Carbon::parse('2026-06-02'), Carbon::parse('2026-06-02'));

    expect($data['rows'][0]['status'])->toBe('Leave')
        ->and($data['totals']['leave'])->toBe(1);
});

it('attributes an overnight clock-out to the day the shift started', function () {
    $user = User::factory()->create();

    logAt($user, 'clockin', '2026-06-01 14:49:00');
    logAt($user, 'clockout', '2026-06-02 00:09:00'); // crosses midnight

    $rows = collect(app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-02'))['rows'])
        ->keyBy(fn (array $row): string => $row['date']->toDateString());

    // The shift is logged on its start day, with the next-day clock-out flagged.
    expect($rows['2026-06-01']['time_in'])->toBe('02:49 PM')
        ->and($rows['2026-06-01']['time_out'])->toBe('12:09 AM')
        ->and($rows['2026-06-01']['overnight'])->toBeTrue()
        ->and($rows['2026-06-01']['hours'])->toBe(8.33) // 9h20m gross − 1h lunch
        ->and($rows['2026-06-01']['status'])->toBe('Present');

    // The next day is NOT polluted by that stranded early-morning clock-out.
    expect($rows['2026-06-02']['time_in'])->toBeNull()
        ->and($rows['2026-06-02']['time_out'])->toBeNull();
});

it('does not steal the overnight clock-out from the next day\'s own shift', function () {
    $user = User::factory()->create();

    logAt($user, 'clockin', '2026-06-01 14:49:00');
    logAt($user, 'clockout', '2026-06-02 00:09:00');
    logAt($user, 'clockin', '2026-06-02 14:39:00'); // next day's own shift
    logAt($user, 'clockout', '2026-06-03 02:00:00');

    $rows = collect(app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-02'))['rows'])
        ->keyBy(fn (array $row): string => $row['date']->toDateString());

    expect($rows['2026-06-01']['time_in'])->toBe('02:49 PM')
        ->and($rows['2026-06-01']['time_out'])->toBe('12:09 AM')
        ->and($rows['2026-06-02']['time_in'])->toBe('02:39 PM')
        ->and($rows['2026-06-02']['time_out'])->toBe('02:00 AM')
        ->and($rows['2026-06-02']['overnight'])->toBeTrue();
});

it('pairs a shift that crosses the end of the requested range', function () {
    $user = User::factory()->create();

    logAt($user, 'clockin', '2026-06-01 22:00:00');
    logAt($user, 'clockout', '2026-06-02 06:00:00'); // outside the single-day range

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['time_in'])->toBe('10:00 PM')
        ->and($row['time_out'])->toBe('06:00 AM')
        ->and($row['overnight'])->toBeTrue()
        ->and($row['hours'])->toBe(7.0); // 8h gross − 1h lunch
});

it('does not show a prior night\'s early clock-out as this day\'s clock-out', function () {
    $user = User::factory()->create();

    logAt($user, 'clockin', '2026-06-01 22:00:00');
    logAt($user, 'clockout', '2026-06-02 06:00:00'); // belongs to Jun 1's shift

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-02'), Carbon::parse('2026-06-02'))['rows'][0];

    expect($row['time_in'])->toBeNull()
        ->and($row['time_out'])->toBeNull();
});

it('still surfaces an orphan clock-out on its own day', function () {
    $user = User::factory()->create();

    logAt($user, 'clockout', '2026-06-01 17:00:00'); // no matching clock-in anywhere

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['time_in'])->toBeNull()
        ->and($row['time_out'])->toBe('05:00 PM')
        ->and($row['hours'])->toBe(0.0)
        ->and($row['status'])->toBe('Present');
});

it('clamps an early clock-in to the scheduled start', function () {
    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '10:00', 'time_out' => '18:00']);

    logAt($user, 'clockin', '2026-06-01 08:00:00'); // 2h early
    logAt($user, 'clockout', '2026-06-01 18:00:00');

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['hours'])->toBe(7.0) // 10:00–18:00 = 8h − 1h lunch; the early 2h is not paid
        ->and($row['late'])->toBe(0);
});

it('clamps a late clock-out to the scheduled end (overtime is not auto-counted)', function () {
    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '10:00', 'time_out' => '18:00']);

    logAt($user, 'clockin', '2026-06-01 10:00:00');
    logAt($user, 'clockout', '2026-06-01 20:00:00'); // 2h past schedule

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['hours'])->toBe(7.0) // capped at 18:00; the extra 2h is overtime via request
        ->and($row['undertime'])->toBe(0);
});

it('falls back to the raw worked span when no schedule is set', function () {
    $user = User::factory()->create(); // no schedule

    logAt($user, 'clockin', '2026-06-01 08:00:00');
    logAt($user, 'clockout', '2026-06-01 18:00:00');

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['hours'])->toBe(9.0); // full 10h span − 1h lunch, unclamped
});

it('clamps against a schedule that runs past midnight', function () {
    $user = User::factory()->create();
    $user->userData()->create(['time_in' => '22:00', 'time_out' => '06:00']);

    logAt($user, 'clockin', '2026-06-01 22:00:00');
    logAt($user, 'clockout', '2026-06-02 06:00:00');

    $row = collect(app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-02'))['rows'])
        ->first(fn (array $r): bool => $r['date']->toDateString() === '2026-06-01');

    expect($row['hours'])->toBe(7.0) // 22:00–06:00 = 8h − 1h lunch
        ->and($row['overnight'])->toBeTrue();
});

it('sums approved overtime hours onto the day', function () {
    $user = User::factory()->create();
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => '2026-06-01',
        'hours' => 2.5,
    ]);

    $row = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'))['rows'][0];

    expect($row['overtime'])->toBe(2.5);
});

it('breaks overtime down per status while totalling only approved hours', function () {
    $user = User::factory()->create();

    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => '2026-06-01',
        'hours' => 2.0,
    ]);
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'request_date' => '2026-06-01',
        'hours' => 1.5,
    ]);
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'request_date' => '2026-06-01',
        'hours' => 0.5,
    ]);
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::CANCELLED, // excluded entirely
        'request_date' => '2026-06-01',
        'hours' => 9.0,
    ]);

    $data = app(DtrService::class)->build($user, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01'));
    $row = $data['rows'][0];

    $byStatus = collect($row['overtime_breakdown'])->keyBy(fn (array $entry): string => $entry['status']->value);

    expect($row['overtime'])->toBe(2.0) // approved only
        ->and($data['totals']['overtime'])->toBe(2.0)
        ->and($data['totals']['overtime_pending'])->toBe(2.0) // 1.5 + 0.5 for-approval
        ->and($byStatus->get('approved')['hours'])->toBe(2.0)
        ->and($byStatus->get('forapproval')['hours'])->toBe(2.0) // 1.5 + 0.5 summed
        ->and($byStatus->has('cancelled'))->toBeFalse();
});
