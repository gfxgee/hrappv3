<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\HrOverview;
use App\Filament\Widgets\Hr\HeadcountByDepartmentChartWidget;
use App\Filament\Widgets\Hr\HrStatsWidget;
use App\Filament\Widgets\Hr\LeaveRequestsChartWidget;
use App\Filament\Widgets\Hr\OvertimeByDepartmentChartWidget;
use App\Filament\Widgets\Hr\PraiseLeaderboardWidget;
use App\Filament\Widgets\Hr\StalePendingApprovalsWidget;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\Praise;
use App\Models\PraiseReaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function hrManager(): User
{
    Role::findOrCreate('hr');
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('hr');

    return $user;
}

it('lets managers access the hr overview and renders it', function () {
    $this->actingAs(hrManager());

    expect(HrOverview::canAccess())->toBeTrue();

    Livewire::test(HrOverview::class)->assertSuccessful();
});

it('blocks regular employees from the hr overview and its widgets', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    expect(HrOverview::canAccess())->toBeFalse()
        ->and(HrStatsWidget::canView())->toBeFalse()
        ->and(LeaveRequestsChartWidget::canView())->toBeFalse()
        ->and(OvertimeByDepartmentChartWidget::canView())->toBeFalse()
        ->and(StalePendingApprovalsWidget::canView())->toBeFalse()
        ->and(PraiseLeaderboardWidget::canView())->toBeFalse()
        ->and(HeadcountByDepartmentChartWidget::canView())->toBeFalse();
});

it('allows managers to view every hr widget', function () {
    $this->actingAs(hrManager());

    expect(HrStatsWidget::canView())->toBeTrue()
        ->and(LeaveRequestsChartWidget::canView())->toBeTrue()
        ->and(StalePendingApprovalsWidget::canView())->toBeTrue();
});

it('counts employees hired this month', function () {
    $this->actingAs(hrManager());

    User::factory()->create(['status' => 'active', 'date_hired' => now()->startOfMonth()->addDays(3)->format('Y-m-d')]);
    User::factory()->create(['status' => 'active', 'date_hired' => now()->subMonths(2)->format('Y-m-d')]);

    Livewire::test(HrStatsWidget::class)->assertSee('+1 this month');
});

it('splits pending leave into recent and stale counts', function () {
    $this->actingAs(hrManager());

    LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]); // fresh
    $stale = LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    $stale->created_at = now()->subDays(4);
    $stale->save();

    Livewire::test(HrStatsWidget::class)
        ->assertSee('1 over 48 hrs');
});

it('counts absences excluding clocked-in, on-leave, and inactive staff', function () {
    Carbon\Carbon::setTestNow(now()->next(CarbonInterface::WEDNESDAY)->setHour(10));

    $manager = hrManager();
    $this->actingAs($manager);

    $clockedIn = User::factory()->create(['status' => 'active']);
    AttendanceLog::create(['user_id' => $clockedIn->id, 'type' => 'clockin', 'device' => 'web']);

    $onLeave = User::factory()->create(['status' => 'active']);
    LeaveRequest::factory()->for($onLeave)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    User::factory()->create(['status' => 'active', 'name' => 'Truly Absent']);
    User::factory()->create(['status' => 'inactive']);

    // Absent = manager + Truly Absent (neither clocked in nor on leave) = 2.
    $stats = Livewire::test(HrStatsWidget::class);
    $stats->assertSee('2');

    Carbon\Carbon::setTestNow();
});

it('reports a non-working day instead of absences on weekends and holidays', function () {
    Carbon\Carbon::setTestNow(now()->next(CarbonInterface::SUNDAY)->setHour(10));

    $this->actingAs(hrManager());

    Livewire::test(HrStatsWidget::class)->assertSee('Non-working day');

    Carbon\Carbon::setTestNow();
});

it('buckets leave requests by week and status in the chart', function () {
    $this->actingAs(hrManager());

    LeaveRequest::factory()->create(['status' => AttendanceStatus::APPROVED]);
    LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    LeaveRequest::factory()->create(['status' => AttendanceStatus::REJECTED]);
    LeaveRequest::factory()->create(['status' => AttendanceStatus::CANCELLED]); // excluded

    $widget = Livewire::test(LeaveRequestsChartWidget::class)->instance();
    $data = (fn () => $this->getData())->call($widget);

    expect(array_sum($data['datasets'][0]['data']))->toBe(1)  // approved
        ->and(array_sum($data['datasets'][1]['data']))->toBe(1) // pending
        ->and(array_sum($data['datasets'][2]['data']))->toBe(1) // rejected
        ->and(count($data['labels']))->toBe(LeaveRequestsChartWidget::WEEKS + 1);
});

it('groups current-month approved overtime hours by department', function () {
    $this->actingAs(hrManager());

    $engineering = Department::factory()->create(['name' => 'Engineering']);
    $engineer = User::factory()->create(['status' => 'active', 'department_id' => $engineering->id]);

    OverTimeRequest::factory()->for($engineer)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => now()->startOfMonth()->addDays(1),
        'hours' => 5.0,
    ]);
    OverTimeRequest::factory()->for($engineer)->create([
        'status' => AttendanceStatus::FOR_APPROVAL, // excluded
        'request_date' => now()->startOfMonth()->addDays(2),
        'hours' => 9.0,
    ]);

    $widget = Livewire::test(OvertimeByDepartmentChartWidget::class)->instance();
    $data = (fn () => $this->getData())->call($widget);

    expect($data['labels'])->toContain('Engineering')
        ->and($data['datasets'][0]['data'][array_search('Engineering', $data['labels'], true)])->toBe(5.0);
});

it('lists stale pending leave and overtime with their age', function () {
    $this->actingAs(hrManager());

    $employee = User::factory()->create(['status' => 'active', 'name' => 'Maya Rodriguez']);

    $leave = LeaveRequest::factory()->for($employee)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
    ]);
    $leave->created_at = now()->subDays(5);
    $leave->save();

    $overtime = OverTimeRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'hours' => 4.0,
    ]);
    $overtime->created_at = now()->subDays(3);
    $overtime->save();

    LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]); // fresh — excluded

    $widget = Livewire::test(StalePendingApprovalsWidget::class)->instance();
    $rows = (fn () => $this->staleRequests())->call($widget);

    expect($rows)->toHaveCount(2)
        ->and($rows->first()['age_days'])->toBe(5)
        ->and($rows->pluck('type')->unique()->sort()->values()->all())->toBe(['Leave', 'Overtime']);
});

it('counts only active users in headcount by department', function () {
    $this->actingAs(hrManager());

    $department = Department::factory()->create(['name' => 'Support']);
    User::factory()->count(2)->create(['status' => 'active', 'department_id' => $department->id]);
    User::factory()->create(['status' => 'inactive', 'department_id' => $department->id]);

    $widget = Livewire::test(HeadcountByDepartmentChartWidget::class)->instance();
    $data = (fn () => $this->getData())->call($widget);

    $index = array_search('Support', $data['labels'], true);

    expect($data['datasets'][0]['data'][$index])->toBe(2);
});

it('ranks praise recipients by reactions then praises', function () {
    $this->actingAs(hrManager());

    $star = User::factory()->create(['status' => 'active', 'name' => 'Star Performer']);
    $runnerUp = User::factory()->create(['status' => 'active', 'name' => 'Runner Up']);

    $praise = Praise::factory()->create(['recipient_id' => $star->id, 'praise_session_id' => null]);
    PraiseReaction::factory()->count(3)->create(['praise_id' => $praise->id]);
    Praise::factory()->create(['recipient_id' => $runnerUp->id, 'praise_session_id' => null]);

    $leaders = Livewire::test(PraiseLeaderboardWidget::class)->instance()->leaders();

    expect($leaders[0]['user']->name)->toBe('Star Performer')
        ->and($leaders[0]['reactions'])->toBe(3)
        ->and($leaders[1]['user']->name)->toBe('Runner Up');
});
