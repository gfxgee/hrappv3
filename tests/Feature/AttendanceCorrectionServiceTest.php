<?php

use App\Enum\AttendanceCorrectionType;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\AttendanceCorrectionService;

beforeEach(function () {
    $this->service = new AttendanceCorrectionService;
});

it('creates the missing punch for a missing-clock-in correction', function () {
    $user = User::factory()->create();
    $request = AttendanceCorrectionRequest::factory()->for($user)->create([
        'correction_type' => AttendanceCorrectionType::MISSING_CLOCK_IN,
        'target_log_type' => null,
        'corrected_at' => now()->setTime(9, 0),
    ]);

    $this->service->apply($request);

    $log = AttendanceLog::query()->where('user_id', $user->id)->where('type', 'clockin')->firstOrFail();

    expect($log->created_at->format('H:i'))->toBe('09:00')
        ->and($log->remarks)->toContain("correction #{$request->id}");
});

it('adjusts an existing punch for a wrong-time correction instead of duplicating', function () {
    $user = User::factory()->create();
    $existing = AttendanceLog::create([
        'user_id' => $user->id,
        'type' => 'clockin',
        'device' => 'biometric',
        'created_at' => today()->setTime(8, 5),
    ]);

    $request = AttendanceCorrectionRequest::factory()->for($user)->create([
        'correction_type' => AttendanceCorrectionType::WRONG_TIME,
        'target_log_type' => 'clockin',
        'corrected_at' => today()->setTime(8, 0),
    ]);

    $this->service->apply($request);

    expect(AttendanceLog::query()->where('user_id', $user->id)->where('type', 'clockin')->count())->toBe(1)
        ->and($existing->refresh()->created_at->format('H:i'))->toBe('08:00');
});

it('does nothing for an "other" correction', function () {
    $user = User::factory()->create();
    $request = AttendanceCorrectionRequest::factory()->for($user)->create([
        'correction_type' => AttendanceCorrectionType::OTHER,
        'target_log_type' => null,
        'corrected_at' => now(),
    ]);

    $this->service->apply($request);

    expect(AttendanceLog::query()->where('user_id', $user->id)->count())->toBe(0);
});
