<?php

namespace App\Http\Controllers\Api;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Support\TimeOptions;
use Illuminate\Http\JsonResponse;

class TodaysLeavesController extends Controller
{
    /**
     * Return everyone on leave today as JSON, with their name, reason, and the
     * time span of the leave (start/end time and its duration in hours).
     */
    public function __invoke(): JsonResponse
    {
        $leaves = LeaveRequest::query()
            ->with('user:id,name')
            ->where('request_type', '!=', LeaveType::WFH->value)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->whereNotIn('status', [
                AttendanceStatus::REJECTED->value,
                AttendanceStatus::CANCELLED->value,
            ])
            ->latest('start_date')
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'name' => $leave->user?->name,
                'reason' => $leave->reason,
                'start_time' => $leave->start_time,
                'end_time' => $leave->end_time,
                'duration_hours' => TimeOptions::durationHours($leave->start_time, $leave->end_time),
            ])
            ->values();

        return response()->json($leaves);
    }
}
