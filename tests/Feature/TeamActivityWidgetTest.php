<?php

use App\Enum\LeaveType;
use App\Enum\Mood;
use App\Filament\Widgets\Employee\TeamActivityWidget;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\MoodCheckIn;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('shows today\'s clock-in, mood and filed leave across all employees', function () {
    $me = User::factory()->create(['status' => 'active']);
    $glen = User::factory()->create([
        'department_id' => Department::factory()->create()->id,
        'status' => 'active',
        'first_name' => 'Glen',
        'name' => 'Glen Olivar',
    ]);
    $this->actingAs($me);

    AttendanceLog::create(['user_id' => $glen->id, 'type' => 'clockin', 'device' => 'web']);
    MoodCheckIn::create(['user_id' => $glen->id, 'mood' => Mood::HAPPY->value, 'logged_on' => today()]);
    LeaveRequest::factory()->for($glen)->create([
        'request_type' => LeaveType::VACATION,
        'start_date' => today()->addDays(3),
    ]);

    $texts = (new TeamActivityWidget)->events()->pluck('text');

    expect($texts)->toHaveCount(3)
        ->and($texts->contains(fn (string $t): bool => str_contains($t, 'Glen clocked in')))->toBeTrue()
        ->and($texts->contains(fn (string $t): bool => str_contains($t, 'Glen set their mood to Happy')))->toBeTrue()
        ->and($texts->contains(fn (string $t): bool => str_contains($t, 'Glen filed a Vacation')))->toBeTrue();
});

it('includes employees from any department but excludes anything not from today', function () {
    $me = User::factory()->create(['status' => 'active']);
    $stranger = User::factory()->create(['department_id' => Department::factory()->create()->id, 'status' => 'active', 'first_name' => 'Stranger']);
    $mate = User::factory()->create(['department_id' => Department::factory()->create()->id, 'status' => 'active', 'first_name' => 'Mate']);
    $this->actingAs($me);

    // Different department, today — now INCLUDED (no department scoping).
    AttendanceLog::create(['user_id' => $stranger->id, 'type' => 'clockin', 'device' => 'web']);

    // Yesterday — still excluded (today-only feed).
    $old = AttendanceLog::create(['user_id' => $mate->id, 'type' => 'clockin', 'device' => 'web']);
    $old->forceFill(['created_at' => now()->subDay()])->save();

    $texts = (new TeamActivityWidget)->events()->pluck('text');

    expect($texts)->toHaveCount(1)
        ->and($texts->contains(fn (string $t): bool => str_contains($t, 'Stranger clocked in')))->toBeTrue();
});

it('renders the empty state when there is no activity yet', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(TeamActivityWidget::class)
        ->assertSuccessful()
        ->assertSee('No activity yet today');
});
