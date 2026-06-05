<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Services\DtrService;
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
