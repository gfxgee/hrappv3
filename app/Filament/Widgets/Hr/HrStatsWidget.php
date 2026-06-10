<?php

namespace App\Filament\Widgets\Hr;

use App\Enum\AttendanceStatus;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Org-wide stat cards for the HR Overview: headcount, pending leave,
 * pending overtime, and today's absences.
 */
class HrStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->employeesStat(),
            $this->pendingLeaveStat(),
            $this->pendingOvertimeStat(),
            $this->absentTodayStat(),
        ];
    }

    protected function employeesStat(): Stat
    {
        $total = User::query()->active()->count();

        $hiredThisMonth = User::query()
            ->active()
            ->whereBetween('date_hired', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ])
            ->count();

        return Stat::make('👥 Employees', (string) $total)
            ->description($hiredThisMonth > 0 ? "+{$hiredThisMonth} this month" : 'No new hires this month')
            ->color($hiredThisMonth > 0 ? 'success' : 'gray');
    }

    protected function pendingLeaveStat(): Stat
    {
        $pending = LeaveRequest::query()
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->count();

        $stale = LeaveRequest::query()
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->where('created_at', '<', now()->subHours(48))
            ->count();

        return Stat::make('🗓️ Pending leave', (string) $pending)
            ->description($stale > 0 ? "{$stale} over 48 hrs" : 'All recent')
            ->color($stale > 0 ? 'danger' : 'success');
    }

    protected function pendingOvertimeStat(): Stat
    {
        $pending = OverTimeRequest::query()
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->count();

        $thisWeek = OverTimeRequest::query()
            ->whereBetween('created_at', [now()->subDays(7), now()])
            ->count();

        $lastWeek = OverTimeRequest::query()
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        $delta = $thisWeek - $lastWeek;

        return Stat::make('⏱️ Pending overtime', (string) $pending)
            ->description(match (true) {
                $delta > 0 => "↑ {$delta} more filed vs last wk",
                $delta < 0 => '↓ '.abs($delta).' fewer filed vs last wk',
                default => 'Same filings as last wk',
            })
            ->color($delta > 0 ? 'warning' : 'gray');
    }

    protected function absentTodayStat(): Stat
    {
        $workingDays = app(GeneralSettings::class)->workingDays;

        $isHolidayToday = Holiday::query()
            ->active()
            ->whereDate('date', today())
            ->exists();

        if (! in_array(today()->dayOfWeekIso, $workingDays, true) || $isHolidayToday) {
            return Stat::make('🚫 Absent today', '—')
                ->description('Non-working day')
                ->color('gray');
        }

        $staff = User::query()->active()->count();

        $absent = User::query()
            ->active()
            ->whereDoesntHave('attendanceLogs', fn ($query) => $query->whereDate('created_at', today()))
            ->whereDoesntHave('leaveRequests', fn ($query) => $query
                ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
                ->whereDate('start_date', '<=', today())
                ->whereDate('end_date', '>=', today()))
            ->count();

        $percent = $staff > 0 ? (int) round($absent / $staff * 100) : 0;

        return Stat::make('🚫 Absent today', (string) $absent)
            ->description("{$percent}% of staff")
            ->color($absent > 0 ? 'danger' : 'success');
    }
}
