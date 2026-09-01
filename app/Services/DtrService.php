<?php

namespace App\Services;

use App\Enum\AttendanceStatus;
use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Settings\GeneralSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a Daily Time Record (DTR) for an employee over a date range from
 * their attendance logs, schedule, approved overtime, leave, and holidays.
 *
 * Assumes a single clock-in / clock-out per day; a lunch break (configurable
 * via settings) is deducted from worked hours once the gross span exceeds the
 * threshold.
 */
class DtrService
{
    /** Default lunch hours, seeded into settings. */
    public const LUNCH_HOURS = 1.0;

    /** Default gross-hours threshold above which lunch is deducted, seeded into settings. */
    public const LUNCH_THRESHOLD_HOURS = 5.0;

    public function __construct(protected GeneralSettings $settings) {}

    /**
     * Each row's `overtime` (and the total) counts approved/verified hours
     * only; `overtime_breakdown` additionally sums the day's requests per
     * status (excluding cancelled) so the UI can color them.
     *
     * @return array{
     *     rows: list<array{date: CarbonInterface, day: string, time_in: ?string, time_out: ?string, overnight: bool, hours: float, late: int, undertime: int, overtime: float, overtime_breakdown: list<array{status: AttendanceStatus, hours: float}>, status: string}>,
     *     totals: array{present: int, absent: int, leave: int, hours: float, late: int, undertime: int, overtime: float, overtime_pending: float},
     * }
     */
    public function build(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        /** @var list<int> $workingDays */
        $workingDays = $this->settings->workingDays;

        // Pair punches into shifts (clock-in → next clock-out) over a window
        // widened by a day on each side, so a shift that crosses midnight keeps
        // its clock-out attached to the day it started — and an early-morning
        // clock-out closing the previous day's shift never pollutes today.
        $logs = AttendanceLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from->copy()->subDay()->startOfDay(), $to->copy()->addDay()->endOfDay()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        [$shiftsByInDate, $orphanOutsByDate] = $this->pairShifts($logs);

        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $leaves = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', $this->activeStatuses())
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();

        $overtimeByDate = OverTimeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', AttendanceStatus::CANCELLED->value)
            ->whereBetween('request_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (OverTimeRequest $request): string => $request->request_date->toDateString());

        $userData = $user->userData;

        $rows = [];
        $totals = ['present' => 0, 'absent' => 0, 'leave' => 0, 'hours' => 0.0, 'late' => 0, 'undertime' => 0, 'overtime' => 0.0, 'overtime_pending' => 0.0];

        for ($cursor = $from->copy(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $isWorkingDay = in_array($cursor->dayOfWeekIso, $workingDays, true);

            /** @var Collection<int, array{in: CarbonInterface, out: ?CarbonInterface}>|null $dayShifts */
            $dayShifts = $shiftsByInDate->get($key);

            if ($dayShifts !== null) {
                // First clock-in of the day starts the row; the last shift's
                // clock-out ends it (which may land after midnight, or be null
                // while the shift is still open).
                $in = $dayShifts->first()['in'];
                $out = $dayShifts->last()['out'];
            } else {
                // A clock-out with no matching clock-in (malformed data) still
                // surfaces on its date so the day reads as Present, as before.
                $in = null;
                $out = $orphanOutsByDate->get($key)?->last();
            }

            // The employee's scheduled shift window for this day, if set. An
            // "out" at or before "in" means the schedule runs past midnight.
            $scheduledIn = filled($userData?->time_in) ? Carbon::parse($key.' '.$userData->time_in) : null;
            $scheduledOut = filled($userData?->time_out) ? Carbon::parse($key.' '.$userData->time_out) : null;

            if ($scheduledIn !== null && $scheduledOut !== null && $scheduledOut->lessThanOrEqualTo($scheduledIn)) {
                $scheduledOut = $scheduledOut->addDay();
            }

            $hours = 0.0;
            $late = 0;
            $undertime = 0;

            if ($in && $out) {
                // Clamp the worked span to the scheduled window so early
                // clock-ins and late clock-outs (overtime) don't change paid
                // regular hours. With no schedule set, the raw span is used.
                $start = $scheduledIn !== null ? $in->copy()->max($scheduledIn) : $in;
                $end = $scheduledOut !== null ? $out->copy()->min($scheduledOut) : $out;

                $gross = $end->greaterThan($start) ? abs($start->diffInMinutes($end)) / 60 : 0.0;
                $hours = max(0.0, round($gross >= $this->settings->lunchThresholdHours ? $gross - $this->settings->lunchHours : $gross, 2));

                if ($scheduledIn !== null) {
                    $minutesLate = $in->greaterThan($scheduledIn)
                        ? (int) round(abs($scheduledIn->diffInMinutes($in)))
                        : 0;

                    // The grace period is deducted from the tardiness, so only
                    // the minutes beyond it count (44 late - 15 grace = 29).
                    $late = max(0, $minutesLate - $this->settings->lateGraceMinutes);
                }

                if ($scheduledOut !== null) {
                    $undertime = $out->lessThan($scheduledOut) ? (int) round(abs($out->diffInMinutes($scheduledOut))) : 0;
                }
            }

            /** @var Collection<int, OverTimeRequest> $dayOvertimes */
            $dayOvertimes = $overtimeByDate->get($key) ?? collect();

            $overtime = round((float) $dayOvertimes
                ->whereIn('status', [AttendanceStatus::APPROVED, AttendanceStatus::APPROVED_AND_VERIFIED])
                ->sum('hours'), 2);

            $overtimePending = round((float) $dayOvertimes
                ->where('status', AttendanceStatus::FOR_APPROVAL)
                ->sum('hours'), 2);

            $overtimeBreakdown = $dayOvertimes
                ->groupBy(fn (OverTimeRequest $request): string => $request->status->value)
                ->map(fn (Collection $group): array => [
                    'status' => $group->first()->status,
                    'hours' => round((float) $group->sum('hours'), 2),
                ])
                ->values()
                ->all();

            $holiday = $holidays->get($key);
            $leave = $leaves->first(fn (LeaveRequest $leave): bool => $leave->start_date->lessThanOrEqualTo($cursor)
                && $leave->end_date->greaterThanOrEqualTo($cursor));

            if ($in || $out) {
                $status = 'Present';
                $totals['present']++;
            } elseif ($holiday !== null) {
                $status = 'Holiday';
            } elseif ($leave !== null) {
                $status = 'Leave';
                $totals['leave']++;
            } elseif (! $isWorkingDay) {
                $status = 'Rest day';
            } else {
                $status = 'Absent';
                $totals['absent']++;
            }

            $totals['hours'] += $hours;
            $totals['late'] += $late;
            $totals['undertime'] += $undertime;
            $totals['overtime'] += $overtime;
            $totals['overtime_pending'] += $overtimePending;

            $rows[] = [
                'date' => $cursor->copy(),
                'day' => $cursor->format('D'),
                'time_in' => $in?->format('h:i A'),
                'time_out' => $out?->format('h:i A'),
                'overnight' => $in !== null && $out !== null && $in->toDateString() !== $out->toDateString(),
                'hours' => $hours,
                'late' => $late,
                'undertime' => $undertime,
                'overtime' => $overtime,
                'overtime_breakdown' => $overtimeBreakdown,
                'status' => $status,
            ];
        }

        $totals['hours'] = round($totals['hours'], 2);
        $totals['overtime'] = round($totals['overtime'], 2);
        $totals['overtime_pending'] = round($totals['overtime_pending'], 2);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Pair an ordered punch stream into shifts, each anchored to its clock-in's
     * date. A clock-in is paired with the next clock-out even when that lands on
     * the following day; a clock-out with no open clock-in is returned
     * separately so it can still surface on its own date.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @return array{0: Collection<string, Collection<int, array{in: CarbonInterface, out: ?CarbonInterface}>>, 1: Collection<string, Collection<int, CarbonInterface>>}
     */
    protected function pairShifts(Collection $logs): array
    {
        $shifts = [];
        $orphanOuts = [];
        $openIn = null;

        foreach ($logs as $log) {
            if ($log->type === 'clockin') {
                if ($openIn !== null) {
                    $shifts[] = ['in' => $openIn, 'out' => null]; // clocked in again without clocking out
                }

                $openIn = $log->created_at;
            } elseif ($log->type === 'clockout') {
                if ($openIn !== null) {
                    $shifts[] = ['in' => $openIn, 'out' => $log->created_at];
                    $openIn = null;
                } else {
                    $orphanOuts[] = $log->created_at;
                }
            }
        }

        if ($openIn !== null) {
            $shifts[] = ['in' => $openIn, 'out' => null]; // still on the clock
        }

        return [
            collect($shifts)->groupBy(fn (array $shift): string => $shift['in']->toDateString()),
            collect($orphanOuts)->groupBy(fn (CarbonInterface $out): string => $out->toDateString()),
        ];
    }

    /**
     * Leave/attendance statuses that count toward the record.
     *
     * @return list<string>
     */
    protected function activeStatuses(): array
    {
        return [
            AttendanceStatus::FOR_APPROVAL->value,
            AttendanceStatus::APPROVED->value,
            AttendanceStatus::APPROVED_AND_VERIFIED->value,
        ];
    }
}
