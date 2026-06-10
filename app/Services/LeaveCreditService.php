<?php

namespace App\Services;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\UserData;
use App\Settings\GeneralSettings;
use App\Support\TimeOptions;

class LeaveCreditService
{
    /**
     * Memoized holiday dates ("Y-m-d") for the lifetime of this instance.
     *
     * @var list<string>|null
     */
    private ?array $holidayDates = null;

    /**
     * All holiday dates as "Y-m-d" strings.
     *
     * @return list<string>
     */
    public function holidayDates(): array
    {
        return $this->holidayDates ??= Holiday::query()
            ->active()
            ->orderBy('date')
            ->get(['date'])
            ->map(fn (Holiday $holiday): string => $holiday->date->format('Y-m-d'))
            ->all();
    }

    /**
     * The user_data column holding the quota for a leave type, or null when
     * the type is untracked (no quota / unlimited — e.g. WFH, LWOP).
     */
    public function quotaColumn(LeaveType $type): ?string
    {
        return match ($type) {
            LeaveType::VACATION => 'vacation_leave',
            LeaveType::SICK => 'sick_leave',
            LeaveType::EMERGENCY => 'emergency_leave',
            LeaveType::BEREAVEMENT => 'bereavement_leave',
            LeaveType::MATERNITY => 'maternity_leave',
            LeaveType::PATERNITY => 'paternity_leave',
            default => null,
        };
    }

    /**
     * The length of a standard working day (hours) for the given schedule,
     * derived from time_in/time_out, falling back to 8 hours.
     */
    public function workingHoursFor(?UserData $userData): float
    {
        $default = app(GeneralSettings::class)->standardWorkingHours;

        if ($userData === null) {
            return $default;
        }

        return TimeOptions::durationHours($userData->time_in, $userData->time_out) ?? $default;
    }

    /**
     * The total allotted credit (days) for a leave type, or null when untracked.
     */
    public function totalCredit(User $user, LeaveType $type): ?float
    {
        $column = $this->quotaColumn($type);

        // Untracked type, or no leave balances set up yet → treat as unlimited
        // (we can't enforce a quota that hasn't been defined).
        if ($column === null || $user->userData === null) {
            return null;
        }

        return (float) ($user->userData->{$column} ?? 0);
    }

    /**
     * Days already consumed for a leave type (active requests only).
     * Pass $excludeId to ignore a specific request, e.g. the one being edited.
     */
    public function usedDays(User $user, LeaveType $type, ?int $excludeId = null): float
    {
        $workingHours = $this->workingHoursFor($user->userData);

        $holidays = $this->holidayDates();

        return (float) LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('request_type', $type->value)
            ->whereNotIn('status', [AttendanceStatus::REJECTED->value, AttendanceStatus::CANCELLED->value])
            ->when($excludeId !== null, fn ($query) => $query->where('id', '!=', $excludeId))
            ->get()
            ->sum(fn (LeaveRequest $request): float => $request->durationInDays($workingHours, $holidays));
    }

    /**
     * Remaining credit (days) for a leave type, or null when untracked/unlimited.
     */
    public function remainingDays(User $user, LeaveType $type, ?int $excludeId = null): ?float
    {
        $total = $this->totalCredit($user, $type);

        if ($total === null) {
            return null;
        }

        return max($total - $this->usedDays($user, $type, $excludeId), 0);
    }
}
