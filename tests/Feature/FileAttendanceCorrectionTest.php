<?php

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Filament\Pages\FileAttendanceCorrection;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('lets an employee file an attendance correction request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(FileAttendanceCorrection::class)
        ->fillForm([
            'correction_type' => AttendanceCorrectionType::MISSING_CLOCK_OUT->value,
            'corrected_at' => now()->setTime(18, 0)->toDateTimeString(),
            'reason' => 'Forgot to clock out after my shift.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = AttendanceCorrectionRequest::query()->firstOrFail();

    expect($request->user_id)->toBe($user->id)
        ->and($request->correction_type)->toBe(AttendanceCorrectionType::MISSING_CLOCK_OUT)
        ->and($request->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('requires a type and a reason', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FileAttendanceCorrection::class)
        ->fillForm([
            'correction_type' => null,
            'reason' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['correction_type' => 'required', 'reason' => 'required']);

    expect(AttendanceCorrectionRequest::query()->count())->toBe(0);
});

it('requires the punch type when correcting a wrong time', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FileAttendanceCorrection::class)
        ->fillForm([
            'correction_type' => AttendanceCorrectionType::WRONG_TIME->value,
            'target_log_type' => null,
            'corrected_at' => now()->toDateTimeString(),
            'reason' => 'My clock-in time is wrong.',
        ])
        ->call('create')
        ->assertHasFormErrors(['target_log_type' => 'required']);

    expect(AttendanceCorrectionRequest::query()->count())->toBe(0);
});

it('only lists the signed-in employee\'s own requests', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    AttendanceCorrectionRequest::factory()->for($user)->create();
    AttendanceCorrectionRequest::factory()->for($other)->create();

    $this->actingAs($user);

    Livewire::test(FileAttendanceCorrection::class)
        ->assertCanSeeTableRecords(AttendanceCorrectionRequest::query()->where('user_id', $user->id)->get())
        ->assertCanNotSeeTableRecords(AttendanceCorrectionRequest::query()->where('user_id', $other->id)->get());
});
