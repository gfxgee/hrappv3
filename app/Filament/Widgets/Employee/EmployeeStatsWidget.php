<?php

namespace App\Filament\Widgets\Employee;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use App\Services\LeaveCreditService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The four personal stat cards on the employee dashboard: leave left,
 * sick days, overtime this month, and the next day off.
 */
class EmployeeStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return [
            $this->leaveStat($user, LeaveType::VACATION, '🌴 Leave left'),
            $this->leaveStat($user, LeaveType::SICK, '🤒 Sick days'),
            $this->overtimeStat($user),
            // $this->nextDayOffStat($user),
        ];
    }

    protected function leaveStat(User $user, LeaveType $type, string $label): Stat
    {
        $credits = app(LeaveCreditService::class);

        $total = $credits->totalCredit($user, $type);

        if ($total === null) {
            return Stat::make($label, '—')->description('Untracked');
        }

        $remaining = $credits->remainingDays($user, $type);
        $usedThisYear = $credits->usedDays($user, $type, year: now()->year);

        return Stat::make($label, rtrim(rtrim(number_format($remaining, 1), '0'), '.').' / '.rtrim(rtrim(number_format($total, 1), '0'), '.').' d')
            ->description(rtrim(rtrim(number_format($usedThisYear, 1), '0'), '.').' d used this yr');
    }

    protected function overtimeStat(User $user): Stat
    {
        $hours = (float) OverTimeRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereBetween('request_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('hours');

        $pending = OverTimeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AttendanceStatus::FOR_APPROVAL->value)
            ->count();

        return Stat::make('⏱️ Overtime, '.now()->format('M'), rtrim(rtrim(number_format($hours, 1), '0'), '.').' h')
            ->description($pending > 0 ? "{$pending} ".Str::plural('request', $pending).' pending' : 'No pending requests');
    }

    protected function nextDayOffStat(User $user): Stat
    {
        $nextLeave = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('request_type', '!=', LeaveType::WFH->value)
            ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereDate('start_date', '>=', today())
            ->orderBy('start_date')
            ->value('start_date');

        $nextHoliday = Holiday::query()
            ->active()
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->value('date');

        $candidates = collect([$nextLeave, $nextHoliday])
            ->filter()
            ->map(fn ($date): Carbon => Carbon::parse($date))
            ->sort()
            ->values();

        if ($candidates->isEmpty()) {
            return Stat::make('📅 Next off', '—')->description('Nothing scheduled');
        }

        $next = $candidates->first();
        $days = (int) today()->diffInDays($next->copy()->startOfDay());

        return Stat::make('📅 Next off', $next->format('j M'))
            ->description($days === 0 ? 'Today' : 'in '.$days.' '.Str::plural('day', $days));
    }
}
