<?php

namespace App\Services;

use App\Enum\AttendanceStatus;
use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a Daily Time Record (DTR) for an employee over a date range from
 * their attendance logs, schedule, approved overtime, leave, and holidays.
 *
 * Assumes a single clock-in / clock-out per day; a fixed lunch break is
 * deducted from worked hours once the gross span exceeds the threshold.
 */
class DtrService
{
    /** Hours deducted for lunch once the worked span exceeds the threshold. */
    public const LUNCH_HOURS = 1.0;

    /** Gross worked hours above which the lunch break is deducted. */
    public const LUNCH_THRESHOLD_HOURS = 5.0;

    /**
     * @return array{
     *     rows: list<array{date: CarbonInterface, day: string, time_in: ?string, time_out: ?string, hours: float, late: int, undertime: int, overtime: float, status: string}>,
     *     totals: array{present: int, absent: int, leave: int, hours: float, late: int, undertime: int, overtime: float},
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
        $workingDays = config('leave.working_days', [1, 2, 3, 4, 5]);

        $logsByDate = AttendanceLog::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (AttendanceLog $log): string => $log->created_at->toDateString());

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
            ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereBetween('request_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get()
            ->groupBy(fn (OverTimeRequest $request): string => $request->request_date->toDateString());

        $userData = $user->userData;

        $rows = [];
        $totals = ['present' => 0, 'absent' => 0, 'leave' => 0, 'hours' => 0.0, 'late' => 0, 'undertime' => 0, 'overtime' => 0.0];

        for ($cursor = $from->copy(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $isWorkingDay = in_array($cursor->dayOfWeekIso, $workingDays, true);

            /** @var Collection<int, AttendanceLog>|null $dayLogs */
            $dayLogs = $logsByDate->get($key);
            $in = $dayLogs?->firstWhere('type', 'clockin')?->created_at;
            $out = $dayLogs?->where('type', 'clockout')->last()?->created_at;

            $hours = 0.0;
            $late = 0;
            $undertime = 0;

            if ($in && $out) {
                $gross = abs($in->diffInMinutes($out)) / 60;
                $hours = max(0.0, round($gross >= self::LUNCH_THRESHOLD_HOURS ? $gross - self::LUNCH_HOURS : $gross, 2));

                if (filled($userData?->time_in)) {
                    $scheduledIn = Carbon::parse($key.' '.$userData->time_in);
                    $late = $in->greaterThan($scheduledIn) ? (int) round(abs($scheduledIn->diffInMinutes($in))) : 0;
                }

                if (filled($userData?->time_out)) {
                    $scheduledOut = Carbon::parse($key.' '.$userData->time_out);
                    $undertime = $out->lessThan($scheduledOut) ? (int) round(abs($out->diffInMinutes($scheduledOut))) : 0;
                }
            }

            $overtime = (float) ($overtimeByDate->get($key)?->sum('hours') ?? 0);
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

            $rows[] = [
                'date' => $cursor->copy(),
                'day' => $cursor->format('D'),
                'time_in' => $in?->format('h:i A'),
                'time_out' => $out?->format('h:i A'),
                'hours' => $hours,
                'late' => $late,
                'undertime' => $undertime,
                'overtime' => $overtime,
                'status' => $status,
            ];
        }

        $totals['hours'] = round($totals['hours'], 2);
        $totals['overtime'] = round($totals['overtime'], 2);

        return ['rows' => $rows, 'totals' => $totals];
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
