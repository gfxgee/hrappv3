<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\LeaveCalendar;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    foreach (['superadmin', 'super_admin', 'hr'] as $role) {
        Role::findOrCreate($role);
    }
});

function calendarManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('hr');

    return $user;
}

/**
 * Pull the leave collection for a given Y-m-d from a rendered calendar.
 */
function leavesOnDate($component, string $date)
{
    foreach ($component->instance()->getCalendarWeeks() as $week) {
        foreach ($week as $day) {
            if ($day['date']->toDateString() === $date) {
                return $day['leaves'];
            }
        }
    }

    return collect();
}

it('allows managers and team leaders but denies regular employees', function () {
    $this->actingAs(calendarManager());
    expect(LeaveCalendar::canAccess())->toBeTrue();

    $leader = User::factory()->create();
    $leader->ledDepartments()->attach(Department::factory()->create());
    $this->actingAs($leader);
    expect(LeaveCalendar::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create());
    expect(LeaveCalendar::canAccess())->toBeFalse();
});

it('renders the calendar for a manager', function () {
    $this->actingAs(calendarManager());

    Livewire::test(LeaveCalendar::class)->assertSuccessful();
});

it('places a leave on each day it spans and not outside it', function () {
    $this->actingAs(calendarManager());

    $leave = LeaveRequest::factory()->for(User::factory())->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-12',
    ]);

    $component = Livewire::test(LeaveCalendar::class)->set('year', 2026)->set('month', 6);

    foreach (['2026-06-10', '2026-06-11', '2026-06-12'] as $date) {
        expect(leavesOnDate($component, $date)->pluck('id'))->toContain($leave->id);
    }

    expect(leavesOnDate($component, '2026-06-13')->pluck('id'))->not->toContain($leave->id);
});

it('hides cancelled and rejected leaves by default', function () {
    $this->actingAs(calendarManager());

    $cancelled = LeaveRequest::factory()->for(User::factory())->create([
        'status' => AttendanceStatus::CANCELLED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);

    $component = Livewire::test(LeaveCalendar::class)->set('year', 2026)->set('month', 6);

    expect(leavesOnDate($component, '2026-06-10')->pluck('id'))->not->toContain($cancelled->id);
});

it('filters by leave type', function () {
    $this->actingAs(calendarManager());

    $vacation = LeaveRequest::factory()->for(User::factory())->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);
    $sick = LeaveRequest::factory()->for(User::factory())->create([
        'request_type' => LeaveType::SICK,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);

    $component = Livewire::test(LeaveCalendar::class)
        ->set('year', 2026)->set('month', 6)
        ->set('leaveType', LeaveType::SICK->value);

    $ids = leavesOnDate($component, '2026-06-10')->pluck('id');

    expect($ids)->toContain($sick->id)
        ->and($ids)->not->toContain($vacation->id);
});

it('filters by department', function () {
    $this->actingAs(calendarManager());

    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $leaveA = LeaveRequest::factory()->for(User::factory()->create(['department_id' => $deptA->id]))->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);
    $leaveB = LeaveRequest::factory()->for(User::factory()->create(['department_id' => $deptB->id]))->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);

    $component = Livewire::test(LeaveCalendar::class)
        ->set('year', 2026)->set('month', 6)
        ->set('departmentId', (string) $deptA->id);

    $ids = leavesOnDate($component, '2026-06-10')->pluck('id');

    expect($ids)->toContain($leaveA->id)
        ->and($ids)->not->toContain($leaveB->id);
});

it('scopes a team leader to the departments they lead', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $leader = User::factory()->create();
    $leader->ledDepartments()->attach($deptA);

    $mine = LeaveRequest::factory()->for(User::factory()->create(['department_id' => $deptA->id]))->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);
    $theirs = LeaveRequest::factory()->for(User::factory()->create(['department_id' => $deptB->id]))->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);

    $this->actingAs($leader);

    $component = Livewire::test(LeaveCalendar::class)->set('year', 2026)->set('month', 6);
    $ids = leavesOnDate($component, '2026-06-10')->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('shows holidays on their date regardless of leave filters', function () {
    $this->actingAs(calendarManager());

    $holiday = Holiday::create(['name' => 'Independence Day', 'date' => '2026-06-12']);

    $component = Livewire::test(LeaveCalendar::class)
        ->set('year', 2026)->set('month', 6)
        ->set('departmentId', '999') // a filter that matches no leaves
        ->set('leaveType', LeaveType::SICK->value);

    $holidayOn = function (string $date) use ($component) {
        foreach ($component->instance()->getCalendarWeeks() as $week) {
            foreach ($week as $day) {
                if ($day['date']->toDateString() === $date) {
                    return $day['holiday'];
                }
            }
        }

        return null;
    };

    expect($holidayOn('2026-06-12')?->id)->toBe($holiday->id)
        ->and($holidayOn('2026-06-11'))->toBeNull();
});

it('exposes the holiday for a given date', function () {
    $this->actingAs(calendarManager());
    $holiday = Holiday::create(['name' => 'Founding Day', 'date' => '2026-06-20']);

    $page = Livewire::test(LeaveCalendar::class)->instance();

    expect($page->holidayForDate(Carbon::parse('2026-06-20'))?->id)->toBe($holiday->id)
        ->and($page->holidayForDate(Carbon::parse('2026-06-21')))->toBeNull();
});

it('navigates between months including year rollover', function () {
    $this->actingAs(calendarManager());

    Livewire::test(LeaveCalendar::class)
        ->set('year', 2026)->set('month', 6)
        ->call('nextMonth')->assertSet('month', 7)->assertSet('year', 2026)
        ->call('previousMonth')->assertSet('month', 6)
        ->set('month', 12)
        ->call('nextMonth')->assertSet('month', 1)->assertSet('year', 2027);
});

it('lists the leaves of a day in the detail modal', function () {
    $this->actingAs(calendarManager());

    $employee = User::factory()->create(['name' => 'Calendar Tester']);
    LeaveRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::APPROVED,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-10',
    ]);

    Livewire::test(LeaveCalendar::class)
        ->set('year', 2026)->set('month', 6)
        ->mountAction('dayDetail', ['date' => '2026-06-10'])
        ->assertSee('Calendar Tester');
});
