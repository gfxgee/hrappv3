<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;

const FLOW_URL = 'https://flow.test/invoke';

beforeEach(function () {
    config()->set('services.teams.flow_url', FLOW_URL);
});

it('posts to the Teams flow when a leave is filed', function () {
    Http::fake();
    $user = User::factory()->create(['name' => 'Gee Actub']);

    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-12',
    ]);

    Http::assertSent(fn ($request): bool => $request->url() === FLOW_URL
        && $request['category'] === 'Leave'
        && $request['employee'] === 'Gee Actub'
        && $request['leave_type'] === 'Vacation Leave');
});

it('posts to the Teams flow when overtime is filed', function () {
    Http::fake();
    $user = User::factory()->create(['name' => 'Gee Actub']);

    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'hours' => 3,
    ]);

    Http::assertSent(fn ($request): bool => $request->url() === FLOW_URL
        && $request['category'] === 'Overtime'
        && $request['employee'] === 'Gee Actub');
});

it('sets the approver to the department team leader', function () {
    Http::fake();
    $department = Department::factory()->create();
    $leader = User::factory()->create(['email' => 'teamlead@digitalfeet.com']);
    $leader->ledDepartments()->attach($department);
    $employee = User::factory()->create(['department_id' => $department->id]);

    LeaveRequest::factory()->for($employee)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Http::assertSent(fn ($request): bool => $request['approver'] === 'teamlead@digitalfeet.com');
});

it('falls back to the default approver email when the department has no team leader', function () {
    Http::fake();
    $default = (string) config('services.teams.default_approver');
    $employee = User::factory()->create(['department_id' => null]);

    LeaveRequest::factory()->for($employee)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Http::assertSent(fn ($request): bool => $request['approver'] === $default);
});

it('posts an edited card when a pending leave is edited', function () {
    Http::fake();
    $leave = LeaveRequest::factory()->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'reason' => 'original',
    ]);

    $leave->update(['reason' => 'updated reason', 'start_date' => today()->addDays(5)->toDateString()]);

    Http::assertSent(fn ($request): bool => $request['event'] === 'leave.edited');
});

it('posts a cancelled card when a leave is cancelled', function () {
    Http::fake();
    $leave = LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    $leave->update(['status' => AttendanceStatus::CANCELLED->value]);

    Http::assertSent(fn ($request): bool => $request['event'] === 'leave.cancelled');
});

it('does not post an edit/cancel card when a leave is approved', function () {
    Http::fake();
    $leave = LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    $leave->update(['status' => AttendanceStatus::APPROVED->value]);

    Http::assertNotSent(fn ($request): bool => in_array($request['event'] ?? null, ['leave.edited', 'leave.cancelled'], true));
});

it('posts a cancelled card when overtime is cancelled', function () {
    Http::fake();
    $ot = OverTimeRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    $ot->update(['status' => AttendanceStatus::CANCELLED->value]);

    Http::assertSent(fn ($request): bool => $request['event'] === 'overtime.cancelled');
});

it('does not post when no flow url is configured', function () {
    config()->set('services.teams.flow_url', null);
    Http::fake();

    LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Http::assertNothingSent();
});

it('does not post for leaves that are not pending', function () {
    Http::fake();

    LeaveRequest::factory()->create(['status' => AttendanceStatus::APPROVED]);

    Http::assertNothingSent();
});

it('does not break leave filing when the flow returns an error', function () {
    Http::fake([FLOW_URL => Http::response('boom', 500)]);

    $leave = LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    expect($leave->exists)->toBeTrue()
        ->and(LeaveRequest::find($leave->id))->not->toBeNull();
});
