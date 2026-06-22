<?php

namespace App\Models;

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Models\Concerns\TracksActivity;
use App\Services\AttendanceCorrectionService;
use App\Services\RequestNotifier;
use App\Services\TeamsNotifier;
use Database\Factories\AttendanceCorrectionRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's request to correct their attendance — a missed clock-in/out or
 * an incorrect time. Routed to the employee's team leader and HR for approval;
 * once approved, the fix is applied to the attendance log automatically.
 */
class AttendanceCorrectionRequest extends Model
{
    /** @use HasFactory<AttendanceCorrectionRequestFactory> */
    use HasFactory;

    use TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['user_id', 'correction_type', 'target_log_type', 'corrected_at', 'reason', 'status', 'remarks'];
    }

    /**
     * Notify approvers on filing, and apply the correction to the attendance
     * log once it is approved.
     */
    protected static function booted(): void
    {
        static::created(function (AttendanceCorrectionRequest $request): void {
            if ($request->status === AttendanceStatus::FOR_APPROVAL) {
                app(RequestNotifier::class)->correctionFiled($request);
                app(TeamsNotifier::class)->correctionFiled($request);
            }
        });

        static::updated(function (AttendanceCorrectionRequest $request): void {
            if ($request->wasChanged('status') && $request->status === AttendanceStatus::APPROVED) {
                app(AttendanceCorrectionService::class)->apply($request);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correction_type' => AttendanceCorrectionType::class,
            'corrected_at' => 'datetime',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The attendance-log punch type this correction targets ('clockin' /
     * 'clockout'), or null when there is nothing to auto-apply (type "Other").
     * Derived from the correction type, except "wrong time" where the employee
     * chooses which punch was wrong.
     */
    public function resolveTargetLogType(): ?string
    {
        return match ($this->correction_type) {
            AttendanceCorrectionType::MISSING_CLOCK_IN => 'clockin',
            AttendanceCorrectionType::MISSING_CLOCK_OUT => 'clockout',
            AttendanceCorrectionType::WRONG_TIME => $this->target_log_type,
            AttendanceCorrectionType::OTHER => null,
        };
    }
}
