<?php

use App\Enum\AttendanceStatus;
use App\Enum\HolidayDuration;
use App\Enum\LeaveType;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;

const PORTAL_TOKEN = 'test-portal-token';

beforeEach(function () {
    config()->set('services.portal.token', PORTAL_TOKEN);
    Http::fake();
});

function portalGet(string $route, array $query, ?string $token = PORTAL_TOKEN)
{
    $headers = $token === null ? [] : ['X-Portal-Token' => $token];

    return test()->getJson(route($route, $query), $headers);
}

function leaveOn(User $user, LeaveType $type, string $start, string $end, AttendanceStatus $status = AttendanceStatus::APPROVED, array $extra = []): LeaveRequest
{
    return LeaveRequest::factory()->create([
        'user_id' => $user->id,
        'request_type' => $type,
        'status' => $status,
        'start_date' => $start,
        'end_date' => $end,
        ...$extra,
    ]);
}

it('refuses a request without the right token', function (string $route) {
    $query = ['start' => '2026-11-01', 'end' => '2026-11-30'];

    portalGet($route, $query, token: null)->assertUnauthorized();
    portalGet($route, $query, token: 'wrong')->assertUnauthorized();
})->with(['api.portal.leaves', 'api.portal.holidays']);

it('refuses every request while no token is configured', function () {
    config()->set('services.portal.token', '');

    portalGet('api.portal.leaves', ['start' => '2026-11-01', 'end' => '2026-11-30'], token: '')
        ->assertUnauthorized();
});

it('returns every leave touching the range with the email, type and status', function () {
    $jane = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@digitalfeet.com']);

    $leave = leaveOn($jane, LeaveType::SICK, '2026-11-02', '2026-11-02', extra: [
        'start_time' => '10:00',
        'end_time' => '14:00',
    ]);

    // Starts before the range and ends inside it: still touches the range.
    leaveOn($jane, LeaveType::VACATION, '2026-10-29', '2026-11-03');

    portalGet('api.portal.leaves', ['start' => '2026-11-01', 'end' => '2026-11-30'])
        ->assertOk()
        ->assertJsonCount(2, 'leaves')
        ->assertJsonPath('leaves.0.type', 'vacation')
        ->assertJsonPath('leaves.1', [
            'id' => $leave->id,
            'email' => 'jane@digitalfeet.com',
            'name' => 'Jane Doe',
            'type' => 'sick',
            'status' => 'approved',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'start_time' => '10:00',
            'end_time' => '14:00',
            'duration_hours' => 4,
            'updated_at' => $leave->updated_at->toIso8601String(),
        ]);
});

it('includes a leave still awaiting approval', function () {
    leaveOn(User::factory()->create(), LeaveType::SICK, '2026-11-05', '2026-11-05', AttendanceStatus::FOR_APPROVAL);

    portalGet('api.portal.leaves', ['start' => '2026-11-01', 'end' => '2026-11-30'])
        ->assertOk()
        ->assertJsonPath('leaves.0.status', 'forapproval');
});

it('leaves out work from home, rejected and cancelled requests, and other dates', function () {
    $user = User::factory()->create();

    leaveOn($user, LeaveType::WFH, '2026-11-04', '2026-11-04');
    leaveOn($user, LeaveType::SICK, '2026-11-05', '2026-11-05', AttendanceStatus::REJECTED);
    leaveOn($user, LeaveType::VACATION, '2026-11-06', '2026-11-06', AttendanceStatus::CANCELLED);
    leaveOn($user, LeaveType::VACATION, '2026-12-01', '2026-12-01');
    $kept = leaveOn($user, LeaveType::EMERGENCY, '2026-11-09', '2026-11-09', AttendanceStatus::APPROVED_AND_VERIFIED);

    portalGet('api.portal.leaves', ['start' => '2026-11-01', 'end' => '2026-11-30'])
        ->assertOk()
        ->assertJsonCount(1, 'leaves')
        ->assertJsonPath('leaves.0.id', $kept->id)
        ->assertJsonPath('leaves.0.status', 'verified');
});

it('returns the observed holidays in the range with their duration', function () {
    $christmasEve = Holiday::factory()->create([
        'name' => 'Christmas Eve',
        'date' => '2026-12-24',
        'duration' => HolidayDuration::SECOND_HALF,
    ]);
    Holiday::factory()->create(['name' => 'Rizal Day', 'date' => '2026-12-30']);
    Holiday::factory()->create(['name' => 'Not observed', 'date' => '2026-12-08', 'is_active' => false]);
    Holiday::factory()->create(['name' => 'Next year', 'date' => '2027-01-01']);

    portalGet('api.portal.holidays', ['start' => '2026-12-01', 'end' => '2026-12-31'])
        ->assertOk()
        ->assertExactJson([
            'holidays' => [
                ['id' => $christmasEve->id, 'date' => '2026-12-24', 'name' => 'Christmas Eve', 'duration' => 'second_half'],
                ['id' => Holiday::where('name', 'Rizal Day')->value('id'), 'date' => '2026-12-30', 'name' => 'Rizal Day', 'duration' => 'full_day'],
            ],
        ]);
});

it('requires a valid range of at most 62 days for leaves', function (array $query, string $field) {
    portalGet('api.portal.leaves', $query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no start' => [['end' => '2026-11-30'], 'start'],
    'no end' => [['start' => '2026-11-01'], 'end'],
    'not a date' => [['start' => 'soon', 'end' => '2026-11-30'], 'start'],
    'reversed' => [['start' => '2026-11-30', 'end' => '2026-11-01'], 'end'],
    'too long' => [['start' => '2026-11-01', 'end' => '2027-01-02'], 'end'],
]);

it('accepts the 60-day window the portal asks for', function () {
    portalGet('api.portal.leaves', ['start' => '2026-11-01', 'end' => '2026-12-30'])
        ->assertOk()
        ->assertJsonPath('leaves', []);
});

it('gives holidays the portal\'s whole four-month window in one call, up to a year', function () {
    // The portal asks for holidays from 8 weeks back to 8 weeks ahead at once.
    portalGet('api.portal.holidays', ['start' => '2026-09-14', 'end' => '2027-01-10'])
        ->assertOk()
        ->assertJsonPath('holidays', []);

    portalGet('api.portal.holidays', ['start' => '2026-01-01', 'end' => '2027-01-02'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end');
});
