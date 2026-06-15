<?php

namespace App\Models;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Concerns\TracksActivity;
use App\Services\RequestNotifier;
use App\Services\TeamsNotifier;
use App\Settings\GeneralSettings;
use App\Support\TimeOptions;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    use TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['user_id', 'request_type', 'start_date', 'end_date', 'start_time', 'end_time', 'reason', 'status'];
    }

    /**
     * Notify the approvers whenever a request is filed for approval.
     */
    protected static function booted(): void
    {
        static::created(function (LeaveRequest $request): void {
            if ($request->status === AttendanceStatus::FOR_APPROVAL) {
                app(RequestNotifier::class)->leaveFiled($request);
                app(TeamsNotifier::class)->leaveFiled($request);
            }
        });

        // Re-notify Teams when the employee cancels or edits a pending request.
        static::updated(function (LeaveRequest $request): void {
            if ($request->wasChanged('status')) {
                if ($request->status === AttendanceStatus::CANCELLED) {
                    app(TeamsNotifier::class)->leaveCancelled($request);
                }

                return;
            }

            if ($request->status === AttendanceStatus::FOR_APPROVAL
                && $request->wasChanged(['request_type', 'reason', 'start_date', 'end_date', 'start_time', 'end_time'])) {
                app(TeamsNotifier::class)->leaveEdited($request);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'request_type' => LeaveType::class,
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
     * How many leave-credit days this request consumes.
     *
     * Only working days count: days of the week not in `config('leave.working_days')`
     * (by default Saturday & Sunday) and any dates in $holidayDates are skipped.
     *
     * - Multi-day requests count each working day in the (inclusive) range.
     * - A single working day with a time range shorter than a working day counts
     *   a fraction (e.g. a 1-hour leave on an 8-hour day = 0.125).
     * - A single working day that fills (or exceeds) a working day counts as 1.
     * - A single non-working day (weekend/holiday) counts as 0.
     *
     * @param  float  $workingHoursPerDay  Length of a standard working day in hours.
     * @param  list<string>  $holidayDates  Holiday dates as "Y-m-d" strings.
     */
    public function durationInDays(float $workingHoursPerDay = 8.0, array $holidayDates = []): float
    {
        if ($this->start_date === null || $this->end_date === null) {
            return 0.0;
        }

        /** @var list<int> $workingDays */
        $workingDays = app(GeneralSettings::class)->workingDays;
        $holidays = array_flip($holidayDates);

        $start = $this->start_date->startOfDay();
        $end = $this->end_date->startOfDay();

        $workingDayCount = 0;

        // Reassign rather than mutate, so this works with CarbonImmutable too.
        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            if (in_array($cursor->dayOfWeekIso, $workingDays, true)
                && ! isset($holidays[$cursor->format('Y-m-d')])) {
                $workingDayCount++;
            }
        }

        // A single calendar day may be a partial day, a weekend, or a holiday.
        if ($start->equalTo($end)) {
            if ($workingDayCount === 0) {
                return 0.0;
            }

            $hours = TimeOptions::durationHours($this->start_time, $this->end_time);

            if ($workingHoursPerDay > 0 && $hours !== null && $hours < $workingHoursPerDay) {
                return round($hours / $workingHoursPerDay, 4);
            }

            return 1.0;
        }

        return (float) $workingDayCount;
    }
}
