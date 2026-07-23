<?php

namespace App\Http\Controllers\Api;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\CelebrationService;
use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only JSON feeds of upcoming events — leaves, birthdays, and holidays —
 * within a look-ahead window. The window defaults to the app's "Coming up"
 * setting and can be overridden per request with `?days=`.
 */
class UpcomingController extends Controller
{
    public function __construct(private readonly CelebrationService $celebrations) {}

    /**
     * Approved/pending leaves starting within the window, soonest first.
     */
    public function leaves(Request $request): JsonResponse
    {
        $windowEnd = today()->addDays($this->windowDays($request));

        $leaves = LeaveRequest::query()
            ->with('user:id,name')
            ->where('request_type', '!=', LeaveType::WFH->value)
            ->whereNotIn('status', [
                AttendanceStatus::REJECTED->value,
                AttendanceStatus::CANCELLED->value,
            ])
            ->whereDate('start_date', '>', today())
            ->whereDate('start_date', '<=', $windowEnd)
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'name' => $leave->user?->name,
                'type' => $leave->request_type?->plainLabel(),
                'reason' => $leave->reason,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'days_until' => (int) today()->diffInDays($leave->start_date),
            ])
            ->values();

        return response()->json($leaves);
    }

    /**
     * Active employees whose birthday falls within the window, soonest first.
     */
    public function birthdays(Request $request): JsonResponse
    {
        $windowDays = $this->windowDays($request);

        $birthdays = User::query()
            ->active()
            ->get(['id', 'name', 'birthday'])
            ->map(function (User $user): ?array {
                $next = CelebrationService::nextAnnualOccurrence($user->birthday);

                if ($next === null) {
                    return null;
                }

                return [
                    'name' => $user->name,
                    'date' => $next->toDateString(),
                    'days_until' => (int) today()->diffInDays($next),
                ];
            })
            ->filter()
            ->filter(fn (array $entry): bool => $entry['days_until'] <= $windowDays)
            ->sortBy('days_until')
            ->values();

        return response()->json($birthdays);
    }

    /**
     * Active holidays falling within the window, soonest first.
     */
    public function holidays(Request $request): JsonResponse
    {
        $windowEnd = today()->addDays($this->windowDays($request));

        $holidays = Holiday::query()
            ->active()
            ->whereBetween('date', [today()->toDateString(), $windowEnd->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'name' => $holiday->name,
                'emoji' => $holiday->emoji ?: '📅',
                'date' => $holiday->date->toDateString(),
                'duration' => $holiday->duration?->label() ?? 'Full day',
                'days_until' => (int) today()->diffInDays($holiday->date),
            ])
            ->values();

        return response()->json($holidays);
    }

    /**
     * Look-ahead window in days: the `days` query param (1–365) when valid,
     * otherwise the configurable "Coming up" setting.
     */
    private function windowDays(Request $request): int
    {
        $days = $request->integer('days');

        if ($days >= 1 && $days <= 365) {
            return $days;
        }

        return app(GeneralSettings::class)->comingUpWindowDays;
    }
}
