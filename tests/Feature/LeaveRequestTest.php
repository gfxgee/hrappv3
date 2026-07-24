<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\FileLeaveRequest;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\LeaveRequests\Pages\EditLeaveRequest;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveCreditService;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function userWithRole(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the leave request resource', function (string $role) {
    $this->actingAs(userWithRole($role));

    expect(LeaveRequestResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('allows a department leader to access the leave request resource without a manager role', function () {
    $leader = User::factory()->create();
    $leader->ledDepartments()->attach(Department::factory()->create());
    $this->actingAs($leader);

    expect(LeaveRequestResource::canAccess())->toBeTrue();
});

it('denies regular users access to the leave request resource', function () {
    $this->actingAs(User::factory()->create());

    expect(LeaveRequestResource::canAccess())->toBeFalse();
});

it('badges the count of pending leave requests', function () {
    $this->actingAs(userWithRole('hr'));

    expect(LeaveRequestResource::getNavigationBadge())->toBeNull();

    LeaveRequest::factory()->count(2)->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    LeaveRequest::factory()->create(['status' => AttendanceStatus::APPROVED]);

    expect(LeaveRequestResource::getNavigationBadge())->toBe('2');
});

it('renders the leave request list for a manager', function () {
    $this->actingAs(userWithRole('hr'));

    Livewire::test(ListLeaveRequests::class)->assertSuccessful();
});

it('renders the leave request edit page', function () {
    $this->actingAs(userWithRole('hr'));
    $leave = LeaveRequest::factory()->create();

    Livewire::test(EditLeaveRequest::class, ['record' => $leave->getRouteKey()])
        ->assertSuccessful();

    expect(LeaveRequestResource::getRecordTitle($leave))->toBeString();
});

it('bulk-approves selected pending leave requests, skipping decided ones', function () {
    $this->actingAs(userWithRole('hr'));

    $pending = LeaveRequest::factory()->count(2)->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    $alreadyApproved = LeaveRequest::factory()->create(['status' => AttendanceStatus::APPROVED]);

    Livewire::test(ListLeaveRequests::class)
        ->callTableBulkAction('approveSelected', $pending->push($alreadyApproved));

    expect($pending[0]->refresh()->status)->toBe(AttendanceStatus::APPROVED)
        ->and($pending[1]->refresh()->status)->toBe(AttendanceStatus::APPROVED)
        ->and($alreadyApproved->refresh()->status)->toBe(AttendanceStatus::APPROVED);
});

it('bulk-rejects selected pending leave requests', function () {
    $this->actingAs(userWithRole('hr'));

    $pending = LeaveRequest::factory()->count(3)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Livewire::test(ListLeaveRequests::class)
        ->callTableBulkAction('rejectSelected', $pending);

    $pending->each(fn ($leave) => expect($leave->refresh()->status)->toBe(AttendanceStatus::REJECTED));
});

it('approves a leave request from the resource table', function () {
    $this->actingAs(userWithRole('hr'));
    $leave = LeaveRequest::factory()->create();

    Livewire::test(ListLeaveRequests::class)
        ->callTableAction('approve', $leave);

    expect($leave->refresh()->status)->toBe(AttendanceStatus::APPROVED);
});

it('lets HR verify an approved leave request', function () {
    $this->actingAs(userWithRole('hr'));
    $leave = LeaveRequest::factory()->create(['status' => AttendanceStatus::APPROVED]);

    Livewire::test(ListLeaveRequests::class)
        ->callTableAction('verify', $leave);

    expect($leave->refresh()->status)->toBe(AttendanceStatus::APPROVED_AND_VERIFIED);
});

it('only offers verify once a leave is approved', function () {
    $this->actingAs(userWithRole('hr'));
    $pending = LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Livewire::test(ListLeaveRequests::class)
        ->assertTableActionVisible('approve', $pending)
        ->assertTableActionHidden('verify', $pending);
});

it('does not let a team leader verify an approved leave', function () {
    $department = Department::factory()->create();
    $leader = User::factory()->create();
    $leader->ledDepartments()->attach($department);
    $employee = User::factory()->create(['department_id' => $department->id]);
    $leave = LeaveRequest::factory()->for($employee)->create(['status' => AttendanceStatus::APPROVED]);

    $this->actingAs($leader);

    Livewire::test(ListLeaveRequests::class)
        ->assertTableActionHidden('verify', $leave);
});

it('renders the file leave request page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FileLeaveRequest::class)->assertSuccessful();
});

it('files a leave request for the current user as for-approval', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Family trip',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $leave = LeaveRequest::where('user_id', $user->id)->firstOrFail();

    expect($leave->request_type)->toBe(LeaveType::VACATION);
    expect($leave->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('blocks filing a leave that exceeds the remaining credit', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Long holiday',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-24', // 22 days vs 10 credits
        ])
        ->call('create')
        ->assertHasFormErrors(['end_date']);

    expect(LeaveRequest::where('user_id', $user->id)->count())->toBe(0);
});

it('allows filing a leave within the remaining credit', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Short break',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-05', // 3 days vs 10 credits
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LeaveRequest::where('user_id', $user->id)->count())->toBe(1);
});

it('accounts for already-used credit when validating a new leave', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    // Already used 8 working days (Mon 06-01 → next Wed 06-10, weekend excluded).
    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
    ]);
    $this->actingAs($user);

    // Only 2 left; filing 3 more working days (Mon–Wed) must fail.
    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Trip',
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-17',
        ])
        ->call('create')
        ->assertHasFormErrors(['end_date']);
});

it('excludes the edited leave itself from the credit check', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    $this->actingAs($user);

    // Existing Mon–Fri vacation (5 working days of 10).
    $leave = LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    // Editing the SAME leave to 8 working days (Mon → next Wed) must pass
    // (8 ≤ 10), not fail as if 5 + 8 > 10.
    Livewire::test(FileLeaveRequest::class)
        ->callTableAction('edit', $leave, data: [
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Extended trip',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
            'start_time' => '10:00',
            'end_time' => '18:00',
        ])
        ->assertHasNoTableActionErrors();

    expect($leave->refresh()->durationInDays(8.0))->toBe(8.0);
});

it('does not limit untracked leave types like WFH', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::WFH->value,
            'reason' => 'Remote sprint',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-30', // 28 days, but WFH has no quota
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LeaveRequest::where('user_id', $user->id)->count())->toBe(1);
});

it('syncs end date to start date when start date changes', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FileLeaveRequest::class)
        ->set('data.start_date', '2026-07-15')
        ->assertFormSet(['end_date' => '2026-07-15']);
});

it('defaults start and end date to today on the filing form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Day off',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $leave = LeaveRequest::where('user_id', $user->id)->firstOrFail();

    expect($leave->start_date->isToday())->toBeTrue()
        ->and($leave->end_date->isToday())->toBeTrue();
});

it('defaults start and end time to 10:00 and 18:00 on the filing form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Day off',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $leave = LeaveRequest::where('user_id', $user->id)->firstOrFail();

    expect($leave->start_time)->toBe('10:00')
        ->and($leave->end_time)->toBe('18:00');
});

it('lets an employee edit their own pending leave', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $leave = LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
        'reason' => 'old reason',
    ]);

    Livewire::test(FileLeaveRequest::class)
        ->callTableAction('edit', $leave, data: [
            'request_type' => LeaveType::SICK->value,
            'reason' => 'updated reason',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
        ])
        ->assertHasNoTableActionErrors();

    $leave->refresh();
    expect($leave->request_type)->toBe(LeaveType::SICK);
    expect($leave->reason)->toBe('updated reason');
});

it('hides edit and cancel actions once a leave is rejected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $leave = LeaveRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::REJECTED,
    ]);

    Livewire::test(FileLeaveRequest::class)
        ->assertTableActionHidden('edit', $leave)
        ->assertTableActionHidden('cancel', $leave);
});

it('hides edit and cancel actions once a leave is approved', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $leave = LeaveRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
    ]);

    Livewire::test(FileLeaveRequest::class)
        ->assertTableActionHidden('edit', $leave)
        ->assertTableActionHidden('cancel', $leave);
});

it('opens a read-only view modal from the leave list', function () {
    $this->actingAs(userWithRole('hr'));
    $leave = LeaveRequest::factory()->create();

    Livewire::test(ListLeaveRequests::class)
        ->assertTableActionVisible('view', $leave)
        ->callTableAction('view', $leave)
        ->assertHasNoTableActionErrors();
});

it('cancels a leave and restores the credit', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    $this->actingAs($user);

    $leave = LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
        'start_date' => '2026-06-01', // Monday
        'end_date' => '2026-06-01',
    ]);

    // Before cancel: 1 day used, 9 remaining
    $before = collect((new FileLeaveRequest)->getLeaveCredits())->keyBy('label');
    expect($before[LeaveType::VACATION->label()]['used'])->toBe(1.0);

    Livewire::test(FileLeaveRequest::class)
        ->callTableAction('cancel', $leave);

    expect($leave->refresh()->status)->toBe(AttendanceStatus::CANCELLED);

    $after = collect((new FileLeaveRequest)->getLeaveCredits())->keyBy('label');
    expect($after[LeaveType::VACATION->label()]['used'])->toBe(0.0)
        ->and($after[LeaveType::VACATION->label()]['remaining'])->toBe(10.0);
});

it('only lists the current user\'s leaves on the page', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = LeaveRequest::factory()->for($user)->create();
    $theirs = LeaveRequest::factory()->for($other)->create();

    $this->actingAs($user);

    Livewire::test(FileLeaveRequest::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('decrements the tracked credit after filing a leave through the page', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'sick_leave' => 5,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    $this->actingAs($user);

    $page = Livewire::test(FileLeaveRequest::class)
        ->fillForm([
            'request_type' => LeaveType::VACATION->value,
            'reason' => 'Family trip',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $credits = collect($page->instance()->getLeaveCredits())->keyBy('label');

    // Jul 1 → Jul 3 inclusive = 3 calendar days.
    expect($credits[LeaveType::VACATION->label()]['used'])->toBe(3.0)
        ->and($credits[LeaveType::VACATION->label()]['remaining'])->toBe(7.0);
});

it('counts WFH usage even though it has no quota column', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::FOR_APPROVAL,
        'start_date' => '2026-06-01', // Monday
        'end_date' => '2026-06-01',
    ]);
    $this->actingAs($user);

    $credits = collect((new FileLeaveRequest)->getLeaveCredits())->keyBy('label');
    $wfh = $credits[LeaveType::WFH->label()] ?? null;

    expect($wfh)->not->toBeNull()
        ->and($wfh['tracked'])->toBeFalse()
        ->and($wfh['used'])->toBe(1.0)
        ->and($wfh['total'])->toBeNull();
});

function makeLeave(array $attributes): LeaveRequest
{
    $leave = new LeaveRequest;
    $leave->forceFill($attributes);

    return $leave;
}

it('counts a single full day as one day', function () {
    $leave = makeLeave([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-01',
        'start_time' => '10:00',
        'end_time' => '18:00',
    ]);

    expect($leave->durationInDays(8.0))->toBe(1.0);
});

it('counts a partial-day leave as a fraction of a day', function () {
    // 10:00–11:00 on an 8-hour working day = 1/8 = 0.125 days
    $leave = makeLeave([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-01',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    expect($leave->durationInDays(8.0))->toBe(0.125);
});

it('counts a multi-day leave by working days, excluding the weekend', function () {
    // Fri 2026-06-05 → Mon 2026-06-08 spans Sat & Sun → 2 working days.
    $leave = makeLeave([
        'start_date' => '2026-06-05',
        'end_date' => '2026-06-08',
    ]);

    expect($leave->durationInDays(8.0))->toBe(2.0);
});

it('counts a full Monday–Friday week as five days', function () {
    $leave = makeLeave([
        'start_date' => '2026-06-01', // Monday
        'end_date' => '2026-06-05',   // Friday
    ]);

    expect($leave->durationInDays(8.0))->toBe(5.0);
});

it('counts a single weekend day as zero', function () {
    $leave = makeLeave([
        'start_date' => '2026-06-06', // Saturday
        'end_date' => '2026-06-06',
        'start_time' => '10:00',
        'end_time' => '18:00',
    ]);

    expect($leave->durationInDays(8.0))->toBe(0.0);
});

it('excludes holidays from the working-day count', function () {
    // Mon–Fri with Wed (2026-06-03) as a holiday → 4 working days.
    $leave = makeLeave([
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    expect($leave->durationInDays(8.0, ['2026-06-03']))->toBe(4.0);
});

it('respects a configurable working-day set', function () {
    GeneralSettings::fake(['workingDays' => [1, 2, 3, 4, 5, 6]]); // include Saturday

    $leave = makeLeave([
        'start_date' => '2026-06-06', // Saturday
        'end_date' => '2026-06-06',
        'start_time' => '10:00',
        'end_time' => '18:00',
    ]);

    expect($leave->durationInDays(8.0))->toBe(1.0);
});

it('deducts only a fraction of a credit for a one-hour emergency leave', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'emergency_leave' => 5,
        'time_in' => '10:00',
        'time_out' => '18:00', // 8-hour working day
    ]);
    $this->actingAs($user);

    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::EMERGENCY,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-01', // Monday
        'end_date' => '2026-06-01',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $credits = collect((new FileLeaveRequest)->getLeaveCredits())->keyBy('label');

    expect($credits[LeaveType::EMERGENCY->label()]['used'])->toBe(0.13)
        ->and($credits[LeaveType::EMERGENCY->label()]['remaining'])->toBe(4.87);
});

it('computes leave credits with usage, excluding rejected leaves', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'sick_leave' => 5,
        'emergency_leave' => 3,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);

    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-01', // Monday
        'end_date' => '2026-06-01',
    ]);
    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::REJECTED,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-01',
    ]);

    $this->actingAs($user);

    $credits = collect(Livewire::test(FileLeaveRequest::class)->instance()->getLeaveCredits())
        ->keyBy('label');

    // One approved single-day leave = 1 day; the rejected one is excluded.
    expect($credits[LeaveType::VACATION->label()]['total'])->toBe(10.0)
        ->and($credits[LeaveType::VACATION->label()]['used'])->toBe(1.0)
        ->and($credits[LeaveType::VACATION->label()]['remaining'])->toBe(9.0);
});

it('counts only the current calendar year against annual credits', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);

    // Prior-year usage (e.g. imported history) must not eat into this year's quota.
    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->subYear()->toDateString(),
    ]);

    // A current-year leave on a weekday counts as usual.
    $thisYearWeekday = now()->startOfYear()->next(Carbon::MONDAY);
    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => $thisYearWeekday->toDateString(),
        'end_date' => $thisYearWeekday->toDateString(),
    ]);

    $this->actingAs($user);

    $service = app(LeaveCreditService::class);
    $credits = collect((new FileLeaveRequest)->getLeaveCredits())->keyBy('label');

    // Only the current-year day is counted; last year's is ignored.
    expect($credits[LeaveType::VACATION->label()]['used'])->toBe(1.0)
        ->and($credits[LeaveType::VACATION->label()]['remaining'])->toBe(9.0)
        ->and($service->usedDays($user, LeaveType::VACATION, null, now()->subYear()->year))->toBe(1.0)
        ->and($service->remainingDays($user, LeaveType::VACATION))->toBe(9.0);
});

it('excludes holidays when computing used credit', function () {
    $user = User::factory()->create();
    $user->userData()->create([
        'vacation_leave' => 10,
        'time_in' => '10:00',
        'time_out' => '18:00',
    ]);
    Holiday::create(['name' => 'Mid-week Holiday', 'date' => '2026-06-03']);

    LeaveRequest::factory()->for($user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-01', // Monday
        'end_date' => '2026-06-05',   // Friday (Wed 06-03 is a holiday)
    ]);
    $this->actingAs($user);

    $credits = collect((new FileLeaveRequest)->getLeaveCredits())->keyBy('label');

    // Mon–Fri minus the Wednesday holiday = 4 working days.
    expect($credits[LeaveType::VACATION->label()]['used'])->toBe(4.0)
        ->and($credits[LeaveType::VACATION->label()]['remaining'])->toBe(6.0);
});
