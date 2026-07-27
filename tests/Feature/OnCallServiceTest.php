<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OnCallMember;
use App\Models\User;
use App\Services\OnCallService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Build a 4-person roster (A, B, C, D) in order and return them by name.
 *
 * @return array<string, User>
 */
function makeRoster(): array
{
    $users = [];

    foreach (['A', 'B', 'C', 'D'] as $index => $name) {
        $user = User::factory()->create(['name' => "Dev {$name}", 'status' => 'active']);
        OnCallMember::create(['user_id' => $user->id, 'position' => $index + 1]);
        $users[$name] = $user;
    }

    return $users;
}

function mondayOf(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);
}

/**
 * Persist assignments week by week and return the on-call name per week.
 *
 * @return list<?string>
 */
function assignWeeks(CarbonImmutable $firstMonday, int $weeks): array
{
    $service = app(OnCallService::class);
    $names = [];

    for ($i = 0; $i < $weeks; $i++) {
        $assignment = $service->assignmentForWeek($firstMonday->addWeeks($i), persist: true);
        $names[] = $assignment?->user?->name;
    }

    return $names;
}

it('rotates through the roster in order when nobody is away', function () {
    makeRoster();

    expect(assignWeeks(mondayOf('2026-08-03'), 5))
        ->toBe(['Dev A', 'Dev B', 'Dev C', 'Dev D', 'Dev A']);
});

it('defers a member who is out the whole week, then gives them the next week', function () {
    $users = makeRoster();

    // Dev B is on leave for the entire second week (Mon–Fri).
    $week2 = mondayOf('2026-08-03')->addWeek();
    LeaveRequest::factory()->create([
        'user_id' => $users['B']->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => $week2,
        'end_date' => $week2->addDays(4),
    ]);

    // Expected: B is skipped in week 2 (C covers), then B takes week 3, then D.
    expect(assignWeeks(mondayOf('2026-08-03'), 4))
        ->toBe(['Dev A', 'Dev C', 'Dev B', 'Dev D']);
});

it('keeps a member on-call when they are out only part of the week', function () {
    $users = makeRoster();

    // Dev A is out only Mon–Tue of week 1 — still available for the week.
    $week1 = mondayOf('2026-08-03');
    LeaveRequest::factory()->create([
        'user_id' => $users['A']->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => $week1,
        'end_date' => $week1->addDay(),
    ]);

    expect(assignWeeks($week1, 1))->toBe(['Dev A']);
});

it('projects each member\'s next on-call week in rotation order', function () {
    $users = makeRoster();
    $service = app(OnCallService::class);

    $next = $service->nextOnCallWeekByUser();
    $currentWeek = $service->weekStart(today());

    expect($next[$users['A']->id]->toDateString())->toBe($currentWeek->toDateString())
        ->and($next[$users['B']->id]->toDateString())->toBe($currentWeek->addWeek()->toDateString())
        ->and($next[$users['C']->id]->toDateString())->toBe($currentWeek->addWeeks(2)->toDateString())
        ->and($next[$users['D']->id]->toDateString())->toBe($currentWeek->addWeeks(3)->toDateString());
});

it('returns null when the roster is empty', function () {
    expect(app(OnCallService::class)->pickForWeek(mondayOf('2026-08-03')))->toBeNull();
});

it('keeps the owner as the effective on-call on days they are present', function () {
    $users = makeRoster();
    $service = app(OnCallService::class);

    $effective = $service->onCallForDate(today());

    expect($effective['user']->id)->toBe($users['A']->id)
        ->and($effective['is_substitute'])->toBeFalse();
});

it('hands the day to the next available dev when the owner is on leave that day', function () {
    $users = makeRoster();
    $service = app(OnCallService::class);

    // Owner (A) is out today only — still owns the week, but not today.
    LeaveRequest::factory()->create([
        'user_id' => $users['A']->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $effective = $service->onCallForDate(today());

    // A still owns the week (out only one day)...
    expect($service->assignmentForWeek(today())->user_id)->toBe($users['A']->id)
        // ...but B stands in for today.
        ->and($effective['user']->id)->toBe($users['B']->id)
        ->and($effective['is_substitute'])->toBeTrue()
        ->and($effective['primary']->id)->toBe($users['A']->id);
});

it('keeps the owner on-call when their leave is only part of the day', function () {
    $users = makeRoster();
    $service = app(OnCallService::class);

    // Owner (A) is only out 10:00–13:00 today — a partial day.
    LeaveRequest::factory()->create([
        'user_id' => $users['A']->id,
        'request_type' => LeaveType::EMERGENCY,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
        'start_time' => '10:00',
        'end_time' => '13:00',
    ]);

    $effective = $service->onCallForDate(today());

    // Still A — a partial-day leave doesn't take them off on-call duty.
    expect($effective['user']->id)->toBe($users['A']->id)
        ->and($effective['is_substitute'])->toBeFalse()
        ->and($service->isAvailableOnDate($users['A'], today()))->toBeTrue();
});

it('treats a full-day timed leave as unavailable', function () {
    $users = makeRoster();
    $service = app(OnCallService::class);

    // Owner (A) is out the whole working day (10:00–18:00) today.
    LeaveRequest::factory()->create([
        'user_id' => $users['A']->id,
        'request_type' => LeaveType::SICK,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
        'start_time' => '10:00',
        'end_time' => '18:00',
    ]);

    expect($service->isAvailableOnDate($users['A'], today()))->toBeFalse()
        ->and($service->onCallForDate(today())['user']->id)->toBe($users['B']->id);
});

it('skips a stand-in who is also on leave that day', function () {
    $users = makeRoster();
    $service = app(OnCallService::class);

    // A (owner) and B both out today → C covers.
    foreach (['A', 'B'] as $name) {
        LeaveRequest::factory()->create([
            'user_id' => $users[$name]->id,
            'request_type' => LeaveType::VACATION,
            'status' => AttendanceStatus::APPROVED,
            'start_date' => today(),
            'end_date' => today(),
        ]);
    }

    expect($service->onCallForDate(today())['user']->id)->toBe($users['C']->id);
});

it('ignores WFH when deciding availability', function () {
    $users = makeRoster();

    // Dev A "WFH" all week — WFH means available, so they still lead the rotation.
    $week1 = mondayOf('2026-08-03');
    LeaveRequest::factory()->create([
        'user_id' => $users['A']->id,
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => $week1,
        'end_date' => $week1->addDays(4),
    ]);

    expect(assignWeeks($week1, 1))->toBe(['Dev A']);
});
