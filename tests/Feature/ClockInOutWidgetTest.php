<?php

use App\Filament\Widgets\ClockInOutWidget;
use App\Models\AttendanceLog;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
});

it('renders the widget', function () {
    Livewire::test(ClockInOutWidget::class)->assertSuccessful();
});

it('reports not_started status when there are no logs today', function () {
    expect((new ClockInOutWidget)->getStatus())->toBe('not_started');
});

it('creates a clockin log when clocking in', function () {
    $user = auth()->user();

    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    expect(AttendanceLog::query()
        ->where('user_id', $user->id)
        ->where('type', 'clockin')
        ->whereDate('created_at', today())
        ->count())->toBe(1);
});

it('does not double-clock-in', function () {
    Livewire::test(ClockInOutWidget::class)
        ->call('clockIn')
        ->call('clockIn');

    expect(AttendanceLog::where('type', 'clockin')->count())->toBe(1);
});

it('cannot clock out without clocking in first', function () {
    Livewire::test(ClockInOutWidget::class)->call('clockOut');

    expect(AttendanceLog::where('type', 'clockout')->count())->toBe(0);
});

it('creates a clockout log after a clock-in', function () {
    Livewire::test(ClockInOutWidget::class)
        ->call('clockIn')
        ->call('clockOut');

    expect(AttendanceLog::where('type', 'clockin')->count())->toBe(1)
        ->and(AttendanceLog::where('type', 'clockout')->count())->toBe(1);
});

it('reports done status once clocked in and out today', function () {
    $user = auth()->user();

    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web']);
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockout', 'device' => 'web']);

    expect((new ClockInOutWidget)->getStatus())->toBe('done');
});

it('does not double-clock-out', function () {
    Livewire::test(ClockInOutWidget::class)
        ->call('clockIn')
        ->call('clockOut')
        ->call('clockOut');

    expect(AttendanceLog::where('type', 'clockout')->count())->toBe(1);
});

it('keeps a night-shift clock-in active across midnight', function () {
    // Clocked in 4 hours ago (could be yesterday 9pm, now 1am next day)
    $user = auth()->user();

    AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockin',
        'device' => 'web',
        'created_at' => now()->subHours(4),
        'updated_at' => now()->subHours(4),
    ]);

    $widget = new ClockInOutWidget;

    expect($widget->getStatus())->toBe('in_progress')
        ->and($widget->getClockInLog())->not->toBeNull();
});

it('lets a night-shift worker clock out past midnight', function () {
    $user = auth()->user();

    // Clock in 10 hours ago
    AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockin',
        'device' => 'web',
        'created_at' => now()->subHours(10),
        'updated_at' => now()->subHours(10),
    ]);

    Livewire::test(ClockInOutWidget::class)->call('clockOut');

    expect(AttendanceLog::where('user_id', $user->id)->where('type', 'clockout')->count())->toBe(1);
});

it('ignores a stale clock-in older than 24 hours', function () {
    $user = auth()->user();

    AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockin',
        'device' => 'web',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    expect((new ClockInOutWidget)->getStatus())->toBe('not_started');
});

it('does not allow a second shift on the same day', function () {
    Livewire::test(ClockInOutWidget::class)
        ->call('clockIn')
        ->call('clockOut')
        ->call('clockIn'); // attempt to start another shift today — blocked

    expect(AttendanceLog::where('type', 'clockin')->count())->toBe(1);
});

it('allows clocking in again on a new day', function () {
    $user = auth()->user();

    // Yesterday's completed shift.
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockout', 'device' => 'web', 'created_at' => now()->subDay()->addHours(8), 'updated_at' => now()->subDay()->addHours(8)]);

    Livewire::test(ClockInOutWidget::class)->call('clockIn');

    expect(AttendanceLog::where('user_id', $user->id)->where('type', 'clockin')->whereDate('created_at', today())->count())->toBe(1);
});

it('resets to a blank state on a new day after a completed shift', function () {
    $this->travelTo('2026-06-10 09:00:00');
    $user = auth()->user();

    // Yesterday's completed shift — still inside the 24h look-back window.
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web', 'created_at' => '2026-06-09 15:00:00', 'updated_at' => '2026-06-09 15:00:00']);
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockout', 'device' => 'web', 'created_at' => '2026-06-09 23:00:00', 'updated_at' => '2026-06-09 23:00:00']);

    $widget = new ClockInOutWidget;

    expect($widget->getClockInLog())->toBeNull()
        ->and($widget->getStatus())->toBe('not_started')
        ->and($widget->canClockIn())->toBeTrue();
});

it('does not pair an open clock-in with an earlier orphan clock-out', function () {
    $this->travelTo('2026-06-24 10:30:00');
    $user = auth()->user();

    // Today's clock-in is written first (lower id)...
    $clockIn = AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web', 'created_at' => '2026-06-24 09:57:00', 'updated_at' => '2026-06-24 09:57:00']);
    // ...then an orphan clock-out from YESTERDAY lands with a higher id but an
    // earlier timestamp (e.g. an out-of-order biometric sync).
    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockout', 'device' => 'web', 'created_at' => '2026-06-23 18:04:00', 'updated_at' => '2026-06-23 18:04:00']);

    $widget = new ClockInOutWidget;

    expect($widget->getStatus())->toBe('in_progress')
        ->and($widget->getClockInLog()?->id)->toBe($clockIn->id)
        ->and($widget->getClockOutLog())->toBeNull()
        ->and($widget->getElapsedSeconds())->toBeGreaterThan(0);
});

it('computes an elapsed time once clocked in', function () {
    $user = auth()->user();

    AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockin',
        'device' => 'web',
        'created_at' => now()->subMinutes(90),
        'updated_at' => now()->subMinutes(90),
    ]);

    $human = (new ClockInOutWidget)->getElapsedHuman();

    expect($human)->toMatch('/^1h \d{2}m$/');
});
