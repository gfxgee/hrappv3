<?php

namespace App\Services;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Settings\GeneralSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Per-employee leave and overtime totals for a date range — the payroll view
 * used to auto-fill payslips.
 *
 * Only decided-and-payable records count: approved and HR-verified. Pending,
 * rejected, and cancelled requests are excluded, so a leave awaiting approval
 * never affects pay. Every active employee is returned, including those with
 * nothing in the range, and every leave type key is always present (zeroed) so
 * a consuming automation can index the payload without null checks.
 */
class LeaveSummaryService
{
    /**
     * Statuses that count towards payroll.
     *
     * @var list<string>
     */
    private const PAYABLE_STATUSES = [
        AttendanceStatus::APPROVED->value,
        AttendanceStatus::APPROVED_AND_VERIFIED->value,
    ];

    public function __construct(private readonly LeaveCreditService $credits) {}

    /**
     * Summarise every active employee's leave and overtime within the range.
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     employee_count: int,
     *     employees: list<array<string, mixed>>,
     * }
     */
    public function summarize(CarbonInterface $start, CarbonInterface $end): array
    {
        $start = CarbonImmutable::parse($start)->startOfDay();
        $end = CarbonImmutable::parse($end)->startOfDay();

        $employees = User::query()
            ->active()
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'email', 'department_id']);

        $leaves = $this->payableLeaves($start, $end, $employees->pluck('id'));
        $overtime = $this->payableOvertime($start, $end, $employees->pluck('id'));
        $holidays = $this->credits->holidayDates();

        $rows = $employees->map(function (User $user) use ($leaves, $overtime, $start, $end, $holidays): array {
            $workingHours = $this->credits->workingHoursFor($user->userData);

            $byType = $this->emptyTypeTotals();

            foreach ($leaves->get($user->id, collect()) as $leave) {
                $key = $leave->request_type?->value;

                if ($key === null || ! isset($byType[$key])) {
                    continue;
                }

                $byType[$key]['days'] = round(
                    $byType[$key]['days'] + $this->daysInRange($leave, $start, $end, $workingHours, $holidays),
                    2,
                );
                $byType[$key]['requests']++;
            }

            $hours = $overtime->get($user->id, collect());

            return [
                'name' => $user->name,
                'display_name' => $user->displayName(),
                'email' => $user->email,
                'department' => $user->department?->name ?? '',
                'leaves' => $byType,
                'total_leave_days' => round(array_sum(array_column($byType, 'days')), 2),
                'overtime_hours' => round((float) $hours->sum('hours'), 2),
                'overtime_requests' => $hours->count(),
            ];
        });

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'employee_count' => $rows->count(),
            'employees' => $rows->values()->all(),
        ];
    }

    /**
     * A zeroed bucket for every leave type, so keys are always present.
     *
     * @return array<string, array{days: float, requests: int}>
     */
    private function emptyTypeTotals(): array
    {
        $totals = [];

        foreach (LeaveType::all() as $type) {
            $totals[$type->value] = ['days' => 0.0, 'requests' => 0];
        }

        return $totals;
    }

    /**
     * Payable leaves overlapping the range, grouped by user id.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, Collection<int, LeaveRequest>>
     */
    private function payableLeaves(CarbonImmutable $start, CarbonImmutable $end, Collection $userIds): Collection
    {
        return LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', self::PAYABLE_STATUSES)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get(['user_id', 'request_type', 'start_date', 'end_date', 'start_time', 'end_time'])
            ->groupBy('user_id');
    }

    /**
     * Payable overtime inside the range, grouped by user id.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, Collection<int, OverTimeRequest>>
     */
    private function payableOvertime(CarbonImmutable $start, CarbonImmutable $end, Collection $userIds): Collection
    {
        return OverTimeRequest::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', self::PAYABLE_STATUSES)
            ->whereDate('request_date', '>=', $start)
            ->whereDate('request_date', '<=', $end)
            ->get(['user_id', 'hours', 'request_date'])
            ->groupBy('user_id');
    }

    /**
     * The leave's working-day cost that falls *inside* the range, so a leave
     * spanning the range boundary is only counted for the days within it.
     *
     * @param  list<string>  $holidays
     */
    private function daysInRange(
        LeaveRequest $leave,
        CarbonImmutable $start,
        CarbonImmutable $end,
        float $workingHours,
        array $holidays,
    ): float {
        if ($leave->start_date === null || $leave->end_date === null) {
            return 0.0;
        }

        $leaveStart = CarbonImmutable::parse($leave->start_date)->startOfDay();
        $leaveEnd = CarbonImmutable::parse($leave->end_date)->startOfDay();

        $from = $leaveStart->greaterThan($start) ? $leaveStart : $start;
        $to = $leaveEnd->lessThan($end) ? $leaveEnd : $end;

        if ($from->greaterThan($to)) {
            return 0.0;
        }

        // A single-day leave is fully described by its own times, so reuse the
        // model's partial-day maths (a 10:00–13:00 leave costs a fraction).
        if ($leaveStart->equalTo($leaveEnd)) {
            return $leave->durationInDays($workingHours, $holidays);
        }

        // Multi-day leave: count only the working days inside the range. Times
        // are ignored here, matching how the model treats multi-day leaves.
        /** @var list<int> $workingDays */
        $workingDays = app(GeneralSettings::class)->workingDays;
        $holidayLookup = array_flip($holidays);
        $count = 0;

        for ($cursor = $from; $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            if (in_array($cursor->dayOfWeekIso, $workingDays, true)
                && ! isset($holidayLookup[$cursor->format('Y-m-d')])) {
                $count++;
            }
        }

        return (float) $count;
    }
}
