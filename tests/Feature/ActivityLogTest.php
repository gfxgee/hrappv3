<?php

use App\Enum\AttendanceStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Spatie\Activitylog\Models\Activity;

it('logs leave-request changes with the acting user and field diff', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $leave = LeaveRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    $leave->update(['status' => AttendanceStatus::APPROVED]);

    $activity = Activity::query()
        ->where('subject_type', LeaveRequest::class)
        ->where('subject_id', $leave->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->log_name)->toBe('hr')
        ->and(data_get($activity->properties->toArray(), 'attributes.status'))->toBe(AttendanceStatus::APPROVED->value);
});

it('never logs user secrets in the activity properties', function () {
    $this->actingAs(User::factory()->create());

    $user = User::factory()->create();
    $user->update(['name' => 'Renamed Person', 'password' => bcrypt('new-secret-pw')]);

    $activity = Activity::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    $json = json_encode($activity->properties->toArray());

    expect($json)->toContain('Renamed Person')          // tracked field logged
        ->not->toContain('password')                    // secret keys excluded
        ->not->toContain('two_factor_secret')
        ->not->toContain('remember_token');
});

it('logs login, logout and failed sign-in attempts', function () {
    $user = User::factory()->create();

    event(new Login('web', $user, false));
    event(new Logout('web', $user));
    event(new Failed('web', null, ['email' => 'attacker@example.com', 'password' => 'x']));

    $auth = Activity::query()->where('log_name', 'auth')->get();

    expect($auth->pluck('event')->all())->toContain('login', 'logout', 'failed_login');

    $failed = $auth->firstWhere('event', 'failed_login');
    expect($failed->causer_id)->toBeNull()
        ->and(data_get($failed->properties->toArray(), 'email'))->toBe('attacker@example.com');
});

it('keeps activity-log entries for one year', function () {
    expect(config('activitylog.delete_records_older_than_days'))->toBe(365);
});
