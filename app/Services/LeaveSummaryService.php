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

                $entry = $this->entryFor($leave, $start, $end, $workingHours, $holidays);

                if ($entry === null) {
                    continue;
                }

                $byType[$key]['days'] = round($byType[$key]['days'] + $entry['days'], 2);
                $byType[$key]['hours'] = round($byType[$key]['hours'] + $entry['hours'], 2);
                $byType[$key]['requests']++;
                $byType[$key]['entries'][] = $entry;
            }

            // Chronological, matching how the payslip lists them.
            foreach ($byType as $key => $bucket) {
                usort($byType[$key]['entries'], fn (array $a, array $b): int => $a['date'] <=> $b['date']);
            }

            $overtimeRows = $overtime->get($user->id, collect())
                ->sortBy(fn (OverTimeRequest $ot): string => $ot->request_date?->toDateString() ?? '')
                ->map(fn (OverTimeRequest $ot): array => [
                    'date' => $ot->request_date?->toDateString() ?? '',
                    'hours' => round((float) $ot->hours, 2),
                    'reason' => (string) ($ot->reason ?? ''),
                ])
                ->values()
                ->all();

            return [
                'name' => $user->name,
                'display_name' => $user->displayName(),
                'email' => $user->email,
                'department' => $user->department?->name ?? '',
                'leaves' => $byType,
                'total_leave_days' => round(array_sum(array_column($byType, 'days')), 2),
                'total_leave_hours' => round(array_sum(array_column($byType, 'hours')), 2),
                'overtime_hours' => round(array_sum(array_column($overtimeRows, 'hours')), 2),
                'overtime_requests' => count($overtimeRows),
                'overtime_entries' => $overtimeRows,
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
            $totals[$type->value] = ['days' => 0.0, 'hours' => 0.0, 'requests' => 0, 'entries' => []];
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
            ->get(['user_id', 'request_type', 'start_date', 'end_date', 'start_time', 'end_time', 'reason'])
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
            ->get(['user_id', 'hours', 'request_date', 'reason'])
            ->groupBy('user_id');
    }

    /**
     * One itemised entry for a leave, measured *inside* the range only — so a
     * leave spanning the range boundary contributes just its in-range days.
     * Returns null when nothing of the leave lands on a working day in range.
     *
     * @param  list<string>  $holidays
     * @return array{date: string, end_date: string, days: float, hours: float, reason: string}|null
     */
    private function entryFor(
        LeaveRequest $leave,
        CarbonImmutable $start,
        CarbonImmutable $end,
        float $workingHours,
        array $holidays,
    ): ?array {
        if ($leave->start_date === null || $leave->end_date === null) {
            return null;
        }

        $leaveStart = CarbonImmutable::parse($leave->start_date)->startOfDay();
        $leaveEnd = CarbonImmutable::parse($leave->end_date)->startOfDay();

        $from = $leaveStart->greaterThan($start) ? $leaveStart : $start;
        $to = $leaveEnd->lessThan($end) ? $leaveEnd : $end;

        if ($from->greaterThan($to)) {
            return null;
        }

        // A single-day leave is fully described by its own times, so reuse the
        // model's partial-day maths (a 10:00-13:00 leave costs a fraction).
        if ($leaveStart->equalTo($leaveEnd)) {
            $days = $leave->durationInDays($workingHours, $holidays);
            $workingDates = [$from];
        } else {
            $workingDates = $this->workingDatesBetween($from, $to, $holidays);
            $days = (float) count($workingDates);
        }

        if ($days <= 0 || $workingDates === []) {
            return null; // weekend or holiday only — nothing payable
        }

        // Report the first/last day actually deducted, not the range edge, so a
        // leave clipped to a Saturday boundary still reads as the Monday it hit.
        return [
            'date' => reset($workingDates)->toDateString(),
            'end_date' => end($workingDates)->toDateString(),
            'days' => round($days, 2),
            // Payslips express deductions in hours, so give both.
            'hours' => round($days * $workingHours, 2),
            'reason' => (string) ($leave->reason ?? ''),
        ];
    }

    /**
     * The working dates between two dates, skipping weekends and holidays.
     *
     * @param  list<string>  $holidays
     * @return list<CarbonImmutable>
     */
    private function workingDatesBetween(CarbonImmutable $from, CarbonImmutable $to, array $holidays): array
    {
        /** @var list<int> $workingDays */
        $workingDays = app(GeneralSettings::class)->workingDays;
        $holidayLookup = array_flip($holidays);
        $dates = [];

        for ($cursor = $from; $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            if (in_array($cursor->dayOfWeekIso, $workingDays, true)
                && ! isset($holidayLookup[$cursor->format('Y-m-d')])) {
                $dates[] = $cursor;
            }
        }

        return $dates;
    }
}
