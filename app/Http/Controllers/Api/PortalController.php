<?php

namespace App\Http\Controllers\Api;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\TimeOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Days off for the DF Portal, which counts them against the hours people log
 * in monday (expected hours = contracted minus sick days, leave and holidays).
 *
 * Both endpoints take `?start=YYYY-MM-DD&end=YYYY-MM-DD` (leaves at most 62
 * days, holidays at most a year) and sit behind the portal's shared token
 * (VerifyPortalToken).
 */
class PortalController extends Controller
{
    /** The longest range one leaves call may ask for. The portal asks 60 days at a time. */
    public const MAX_LEAVE_DAYS = 62;

    /** Holidays are few, so the portal asks for its whole window (about four months) at once. */
    public const MAX_HOLIDAY_DAYS = 366;

    /**
     * Every leave touching the range, with the employee's email so the portal
     * can match it to their monday hours. Work-from-home is not a day off, and
     * rejected or cancelled requests never happened, so those are left out. A
     * request still awaiting approval is included: a sick day is a sick day
     * whether or not it has been approved yet.
     */
    public function leaves(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request, self::MAX_LEAVE_DAYS);

        $leaves = LeaveRequest::query()
            ->with('user:id,name,email')
            ->whereNotIn('request_type', [LeaveType::WFH->value, LeaveType::EMPTY->value])
            ->whereNotIn('status', [
                AttendanceStatus::REJECTED->value,
                AttendanceStatus::CANCELLED->value,
            ])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'email' => $leave->user?->email,
                'name' => $leave->user?->name,
                'type' => $leave->request_type?->value,
                'status' => $leave->status?->value,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'start_time' => $leave->start_time,
                'end_time' => $leave->end_time,
                'duration_hours' => TimeOptions::durationHours($leave->start_time, $leave->end_time),
                'updated_at' => $leave->updated_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['leaves' => $leaves]);
    }

    /**
     * The observed (active) holidays in the range. `duration` is `full_day`,
     * `first_half` or `second_half`.
     */
    public function holidays(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request, self::MAX_HOLIDAY_DAYS);

        $holidays = Holiday::query()
            ->active()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
                'duration' => $holiday->duration?->value,
            ])
            ->values();

        return response()->json(['holidays' => $holidays]);
    }

    /**
     * The requested range. Both dates are required, unlike the other API
     * endpoints, so a wrong call from the portal fails loudly instead of
     * quietly reading the wrong month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request, int $maxDays): array
    {
        $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ]);

        $start = CarbonImmutable::parse((string) $request->query('start'))->startOfDay();
        $end = CarbonImmutable::parse((string) $request->query('end'))->startOfDay();

        if ($start->diffInDays($end) + 1 > $maxDays) {
            throw ValidationException::withMessages([
                'end' => "Ask for at most {$maxDays} days at a time.",
            ]);
        }

        return [$start, $end];
    }
}
