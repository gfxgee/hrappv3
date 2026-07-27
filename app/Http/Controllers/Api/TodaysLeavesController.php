<?php

namespace App\Http\Controllers\Api;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Support\TimeOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TodaysLeavesController extends Controller
{
    /**
     * Return everyone on leave on the target date as JSON, with their name,
     * leave type (display label plus the raw enum value), reason, and the time
     * span of the leave (start/end time and its duration in hours). The date
     * defaults to today and can be overridden with
     * `?date=YYYY-MM-DD` (handy for testing the payload on a known-populated
     * date, e.g. when today is a weekend with no leaves).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);

        $leaves = LeaveRequest::query()
            ->with('user:id,name')
            ->where('request_type', '!=', LeaveType::WFH->value)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereNotIn('status', [
                AttendanceStatus::REJECTED->value,
                AttendanceStatus::CANCELLED->value,
            ])
            ->latest('start_date')
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'name' => $leave->user?->name,
                'type' => $leave->request_type?->plainLabel(),
                'type_value' => $leave->request_type?->value,
                'reason' => $leave->reason,
                'start_time' => $leave->start_time,
                'end_time' => $leave->end_time,
                'duration_hours' => TimeOptions::durationHours($leave->start_time, $leave->end_time),
            ])
            ->values();

        return response()->json($leaves);
    }

    /**
     * The target date: the `date` query param when it's a valid date,
     * otherwise today.
     */
    private function resolveDate(Request $request): CarbonImmutable
    {
        if ($request->filled('date')) {
            try {
                return CarbonImmutable::parse((string) $request->query('date'))->startOfDay();
            } catch (Throwable) {
                // Unparseable date → fall back to today.
            }
        }

        return CarbonImmutable::parse(today());
    }
}
