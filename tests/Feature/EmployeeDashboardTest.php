<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Employee\ComingUpWidget;
use App\Filament\Widgets\Employee\EmployeeStatsWidget;
use App\Filament\Widgets\Employee\MyPraiseWidget;
use App\Filament\Widgets\Employee\MyRequestsWidget;
use App\Filament\Widgets\Employee\MyTeamTodayWidget;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\Praise;
use App\Models\User;
use App\Services\LeaveCreditService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->user = User::factory()->create(['status' => 'active', 'first_name' => 'Daniel']);
    $this->actingAs($this->user);
});

it('renders the employee dashboard with its widgets', function () {
    Livewire::test(Dashboard::class)->assertSuccessful();
});

it('greets the user and lists department leaders excluding themselves', function () {
    $department = Department::factory()->create(['name' => 'Engineering']);
    $leader = User::factory()->create(['name' => 'Sarah Park']);
    $department->leaders()->attach([$leader->id, $this->user->id]);
    $this->user->update(['department_id' => $department->id]);

    $page = new Dashboard;

    expect($page->greeting())->toContain('Daniel')
        ->and($page->departmentName())->toBe('Engineering')
        ->and($page->leaderNames())->toBe(['Sarah Park']);
});

it('puts the employee name in the document title but not the page heading', function () {
    $this->user->update(['name' => 'Daniel Cruz']);

    $page = new Dashboard;

    expect($page->getTitle())->toBe('Daniel Cruz · Dashboard')
        ->and($page->getHeading())->toBe('Dashboard');
});

it('renders the employee stats widget', function () {
    Livewire::test(EmployeeStatsWidget::class)->assertSuccessful();
});

it('sums only approved overtime hours for the current month', function () {
    OverTimeRequest::factory()->for($this->user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => now()->startOfMonth()->addDays(2),
        'hours' => 4.0,
    ]);
    OverTimeRequest::factory()->for($this->user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'request_date' => now()->startOfMonth()->addDays(3),
        'hours' => 9.0,
    ]);
    OverTimeRequest::factory()->for($this->user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => now()->subMonthNoOverflow(),
        'hours' => 7.0,
    ]);

    Livewire::test(EmployeeStatsWidget::class)
        ->assertSee('4 h')
        ->assertSee('1 request pending');
});

it('computes the next day off as the sooner of leave and holiday, skipping WFH', function () {
    // The stat card is currently unmounted from getStats(), so the
    // computation is exercised directly to keep it covered.
    Holiday::create(['name' => 'Far Holiday', 'date' => today()->addDays(20)->toDateString()]);
    LeaveRequest::factory()->for($this->user)->create([
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->addDays(2)->toDateString(),
        'end_date' => today()->addDays(2)->toDateString(),
    ]);
    LeaveRequest::factory()->for($this->user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->addDays(5)->toDateString(),
        'end_date' => today()->addDays(6)->toDateString(),
    ]);

    $widget = new EmployeeStatsWidget;
    $stat = (fn () => $this->nextDayOffStat(auth()->user()))->call($widget);

    expect($stat->getValue())->toBe(today()->addDays(5)->format('j M'))
        ->and($stat->getDescription())->toBe('in 5 days');
});

it('scopes used leave days to a calendar year', function () {
    $this->user->userData()->create(['vacation_leave' => 10, 'time_in' => '09:00', 'time_out' => '18:00']);

    LeaveRequest::factory()->for($this->user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => now()->subYear()->startOfWeek()->toDateString(),
        'end_date' => now()->subYear()->startOfWeek()->toDateString(),
    ]);

    $service = app(LeaveCreditService::class);

    expect($service->usedDays($this->user, LeaveType::VACATION))->toBe(1.0)
        ->and($service->usedDays($this->user, LeaveType::VACATION, year: now()->year))->toBe(0.0);
});

it('merges leave and overtime into my requests, newest first', function () {
    $leave = LeaveRequest::factory()->for($this->user)->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::FOR_APPROVAL,
        'start_date' => today()->addDays(10)->toDateString(),
        'end_date' => today()->addDays(12)->toDateString(),
    ]);
    $leave->created_at = now()->subDays(3);
    $leave->save();

    $overtime = OverTimeRequest::factory()->for($this->user)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => today(),
        'hours' => 2.0,
    ]);
    $overtime->created_at = now()->subDay();
    $overtime->save();

    $rows = Livewire::test(MyRequestsWidget::class)->instance()->requests();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toContain('Overtime')
        ->and($rows[1]['status'])->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('does not list other employees requests in my requests', function () {
    LeaveRequest::factory()->create([
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    expect(Livewire::test(MyRequestsWidget::class)->instance()->requests())->toBeEmpty();
});

it('shows team members with the correct status for today', function () {
    $department = Department::factory()->create();
    $this->user->update(['department_id' => $department->id]);

    $inOffice = User::factory()->create(['status' => 'active', 'department_id' => $department->id, 'name' => 'In Office']);
    AttendanceLog::create(['user_id' => $inOffice->id, 'type' => 'clockin', 'device' => 'biometric']);

    $fromHome = User::factory()->create(['status' => 'active', 'department_id' => $department->id, 'name' => 'From Home']);
    AttendanceLog::create(['user_id' => $fromHome->id, 'type' => 'clockin', 'device' => 'web']);

    $remote = User::factory()->create(['status' => 'active', 'department_id' => $department->id, 'name' => 'Remote Worker']);
    LeaveRequest::factory()->for($remote)->create([
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    $sick = User::factory()->create(['status' => 'active', 'department_id' => $department->id, 'name' => 'Sick Person']);
    LeaveRequest::factory()->for($sick)->create([
        'request_type' => LeaveType::SICK,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    $noActivity = User::factory()->create(['status' => 'active', 'department_id' => $department->id, 'name' => 'No Activity']);
    User::factory()->create(['status' => 'active', 'name' => 'Other Dept']); // excluded

    $rows = Livewire::test(MyTeamTodayWidget::class)->instance()->members();
    $byName = $rows->keyBy(fn (array $row): string => $row['user']->name);

    expect($rows)->toHaveCount(5)
        ->and($byName['In Office']['status'])->toBe('In office')
        ->and($byName['From Home']['status'])->toBe('Work from home')
        ->and($byName['Remote Worker']['status'])->toBe('Work from home')
        ->and($byName['Sick Person']['status'])->toBe('Sick')
        ->and($byName['No Activity']['status'])->toBe('—')
        ->and($byName->has('Other Dept'))->toBeFalse();
});

it('lists only praise received by the current user', function () {
    $mine = Praise::factory()->create(['recipient_id' => $this->user->id]);
    Praise::factory()->create(); // someone else's

    $rows = Livewire::test(MyPraiseWidget::class)->instance()->praises();

    expect($rows->pluck('id'))->toContain($mine->id)->toHaveCount(1);
});

it('merges birthdays, anniversaries, and holidays into coming up', function () {
    User::factory()->create([
        'status' => 'active',
        'name' => 'Birthday Person',
        'birthday' => today()->addDays(3)->subYears(30)->format('Y-m-d'),
    ]);
    User::factory()->create([
        'status' => 'active',
        'name' => 'Anniversary Person',
        'date_hired' => today()->addDays(5)->subYears(3)->format('Y-m-d'),
    ]);
    Holiday::create(['name' => 'Near Holiday', 'date' => today()->addDays(7)->toDateString()]);
    Holiday::create(['name' => 'Far Holiday', 'date' => today()->addDays(60)->toDateString()]);

    $rows = Livewire::test(ComingUpWidget::class, ['windowDays' => 14])->instance()->entries();
    $labels = $rows->pluck('label');

    expect($labels->first())->toContain('Birthday Person')
        ->and($labels)->toContain('Anniversary Person — 3 yrs')
        ->and($labels)->toContain('Near Holiday')
        ->and($labels)->not->toContain('Far Holiday');
});

it('skips anniversaries for employees hired this year', function () {
    User::factory()->create([
        'status' => 'active',
        'name' => 'New Hire',
        'date_hired' => today()->addDays(2)->format('Y-m-d'), // future-dated hire this year
    ]);

    $labels = Livewire::test(ComingUpWidget::class)->instance()->entries()->pluck('label');

    expect($labels->filter(fn (string $label): bool => str_contains($label, 'New Hire')))->toBeEmpty();
});
