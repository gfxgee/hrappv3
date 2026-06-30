<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Widgets\Employee\MyTeamTodayWidget;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->dept = Department::factory()->create();
    $this->me = User::factory()->create(['department_id' => $this->dept->id, 'status' => 'active']);
    $this->actingAs($this->me);
});

function teammate(int $departmentId, string $name = 'Teammate'): User
{
    return User::factory()->create([
        'department_id' => $departmentId,
        'status' => 'active',
        'name' => $name,
    ]);
}

it('marks a web clock-in as work from home and a biometric clock-in as in office', function () {
    $web = teammate($this->dept->id, 'Web Worker');
    $office = teammate($this->dept->id, 'Office Worker');
    $idle = teammate($this->dept->id, 'Idle Worker');

    AttendanceLog::create(['user_id' => $web->id, 'type' => 'clockin', 'device' => 'web']);
    AttendanceLog::create(['user_id' => $office->id, 'type' => 'clockin', 'device' => 'biometric']);

    $members = (new MyTeamTodayWidget)->members()->keyBy(fn (array $m): int => $m['user']->id);

    expect($members[$web->id]['status'])->toBe('Work from home')
        ->and($members[$web->id]['color'])->toBe('info')
        ->and($members[$office->id]['status'])->toBe('In office')
        ->and($members[$office->id]['color'])->toBe('success')
        ->and($members[$idle->id]['status'])->toBe('—');
});

it('shows approved sick leave even when the employee has clocked in', function () {
    $sick = teammate($this->dept->id, 'Sick Worker');

    AttendanceLog::create(['user_id' => $sick->id, 'type' => 'clockin', 'device' => 'web']);
    LeaveRequest::factory()->for($sick)->create([
        'request_type' => LeaveType::SICK,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $members = (new MyTeamTodayWidget)->members()->keyBy(fn (array $m): int => $m['user']->id);

    expect($members[$sick->id]['status'])->toBe('Sick');
});

it('shows work from home for an approved WFH leave when not yet clocked in', function () {
    $remote = teammate($this->dept->id, 'Remote Worker');

    LeaveRequest::factory()->for($remote)->create([
        'request_type' => LeaveType::WFH,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $members = (new MyTeamTodayWidget)->members()->keyBy(fn (array $m): int => $m['user']->id);

    expect($members[$remote->id]['status'])->toBe('Work from home');
});
