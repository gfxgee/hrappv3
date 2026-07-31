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

class TodaysWfhController extends Controller
{
    /**
     * Return everyone working from home on the target date as JSON, mirroring
     * the shape of the "on leave today" feed. The date defaults to today and
     * can be overridden with `?date=YYYY-MM-DD`.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);

        $wfh = LeaveRequest::query()
            ->with('user:id,name,display_name')
            ->where('request_type', LeaveType::WFH->value)
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
                'display_name' => $leave->user?->displayName(),
                'reason' => $leave->reason,
                'start_time' => $leave->start_time,
                'end_time' => $leave->end_time,
                'duration_hours' => TimeOptions::durationHours($leave->start_time, $leave->end_time),
            ])
            ->values();

        return response()->json($wfh);
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
