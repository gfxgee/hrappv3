<?php

namespace App\Filament\Pages;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeaveCalendar extends Page
{
    protected string $view = 'filament.pages.leave-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Leave Calendar';

    protected static ?string $title = 'Leave Calendar';

    /** Roles with company-wide visibility. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public int $year;

    public int $month;

    /** Department filter (empty = all accessible departments). */
    public ?string $departmentId = null;

    /** Leave-type filter (empty = all types). */
    public ?string $leaveType = null;

    /** Status filter (empty = all active statuses). */
    public ?string $status = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    /**
     * Managers and team leaders may view the calendar.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasAnyRole(static::MANAGER_ROLES) || $user->isTeamLeader());
    }

    protected function isManager(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    /**
     * Department IDs this user may see. Null means all departments (managers);
     * team leaders are limited to the departments they lead.
     *
     * @return list<int>|null
     */
    protected function accessibleDepartmentIds(): ?array
    {
        if ($this->isManager()) {
            return null;
        }

        return auth()->user()?->ledDepartments()->pluck('departments.id')->all() ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Filter option lists
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int|string, string>
     */
    public function departmentOptions(): array
    {
        $query = Department::query()->orderBy('name');

        $ids = $this->accessibleDepartmentIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function leaveTypeOptions(): array
    {
        return collect(LeaveType::all())
            ->mapWithKeys(fn (LeaveType $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return [
            AttendanceStatus::FOR_APPROVAL->value => 'For Approval',
            AttendanceStatus::APPROVED->value => 'Approved',
            AttendanceStatus::APPROVED_AND_VERIFIED->value => 'Verified',
            AttendanceStatus::CANCELLED->value => 'Cancelled',
            AttendanceStatus::REJECTED->value => 'Rejected',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Month navigation
    |--------------------------------------------------------------------------
    */

    public function previousMonth(): void
    {
        $cursor = Carbon::create($this->year, $this->month, 1)->subMonthNoOverflow();
        $this->year = $cursor->year;
        $this->month = $cursor->month;
    }

    public function nextMonth(): void
    {
        $cursor = Carbon::create($this->year, $this->month, 1)->addMonthNoOverflow();
        $this->year = $cursor->year;
        $this->month = $cursor->month;
    }

    public function goToToday(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    public function getMonthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /*
    |--------------------------------------------------------------------------
    | Calendar data
    |--------------------------------------------------------------------------
    */

    /**
     * Leaves overlapping the given (inclusive) date range, with all filters
     * and the per-user department access scope applied.
     *
     * @return Collection<int, LeaveRequest>
     */
    protected function leavesBetween(Carbon $from, Carbon $to): Collection
    {
        $deptIds = $this->accessibleDepartmentIds();
        $departmentFilter = $this->departmentId;
        $leaveType = $this->leaveType;

        return LeaveRequest::query()
            ->with(['user.department'])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->when(
                filled($this->status),
                fn (Builder $query) => $query->where('status', $this->status),
                fn (Builder $query) => $query->whereIn('status', [
                    AttendanceStatus::FOR_APPROVAL->value,
                    AttendanceStatus::APPROVED->value,
                    AttendanceStatus::APPROVED_AND_VERIFIED->value,
                ]),
            )
            ->when(filled($leaveType), fn (Builder $query) => $query->where('request_type', $leaveType))
            ->whereHas('user', function (Builder $query) use ($deptIds, $departmentFilter): void {
                if ($deptIds !== null) {
                    $query->whereIn('department_id', $deptIds);
                }

                if (filled($departmentFilter)) {
                    $query->where('department_id', $departmentFilter);
                }
            })
            ->get();
    }

    /**
     * Holidays within the given (inclusive) range, keyed by their Y-m-d date.
     *
     * @return Collection<string, Holiday>
     */
    protected function holidaysBetween(Carbon $from, Carbon $to): Collection
    {
        return Holiday::query()
            ->active()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());
    }

    /**
     * The visible month as a grid of weeks (each a list of 7 day cells).
     *
     * @return list<list<array{date: Carbon, inMonth: bool, isToday: bool, isWeekend: bool, holiday: ?Holiday, leaves: Collection<int, LeaveRequest>}>>
     */
    public function getCalendarWeeks(): array
    {
        $first = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $gridStart = $first->copy()->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        $leaves = $this->leavesBetween($gridStart, $gridEnd);
        $holidays = $this->holidaysBetween($gridStart, $gridEnd);

        $weeks = [];
        $week = [];

        for ($day = $gridStart->copy(); $day->lessThanOrEqualTo($gridEnd); $day->addDay()) {
            $current = $day->copy();

            $week[] = [
                'date' => $current,
                'inMonth' => $current->month === $this->month,
                'isToday' => $current->isToday(),
                'isWeekend' => $current->isWeekend(),
                'holiday' => $holidays->get($current->toDateString()),
                'leaves' => $this->leavesOn($leaves, $current),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /**
     * The holiday on a specific date, if any (used by the day-detail modal).
     */
    public function holidayForDate(Carbon $date): ?Holiday
    {
        return Holiday::query()->whereDate('date', $date->toDateString())->first();
    }

    /**
     * Filter a preloaded collection to the leaves covering a single day.
     *
     * @param  Collection<int, LeaveRequest>  $leaves
     * @return Collection<int, LeaveRequest>
     */
    protected function leavesOn(Collection $leaves, Carbon $day): Collection
    {
        return $leaves
            ->filter(fn (LeaveRequest $leave): bool => $leave->start_date !== null
                && $leave->end_date !== null
                && $leave->start_date->lessThanOrEqualTo($day)
                && $leave->end_date->greaterThanOrEqualTo($day))
            ->sortBy(fn (LeaveRequest $leave): string => $leave->user?->name ?? '')
            ->values();
    }

    /**
     * Leaves on a specific date (used by the day-detail modal).
     *
     * @return Collection<int, LeaveRequest>
     */
    public function leavesForDate(Carbon $date): Collection
    {
        return $this->leavesOn($this->leavesBetween($date, $date), $date);
    }

    /**
     * Colour classes for a leave chip, based on its status.
     */
    public function chipClasses(LeaveRequest $leave): string
    {
        return match ($leave->status) {
            AttendanceStatus::APPROVED, AttendanceStatus::APPROVED_AND_VERIFIED => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300',
            AttendanceStatus::FOR_APPROVAL => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
        };
    }

    /**
     * A day-detail modal listing every leave on the chosen date.
     */
    public function dayDetailAction(): Action
    {
        return Action::make('dayDetail')
            ->modalHeading(fn (array $arguments): string => Carbon::parse($arguments['date'])->format('l, F j, Y'))
            ->modalContent(fn (array $arguments) => view('filament.pages.leave-calendar-day', [
                'date' => Carbon::parse($arguments['date']),
                'leaves' => $this->leavesForDate(Carbon::parse($arguments['date'])),
                'holiday' => $this->holidayForDate(Carbon::parse($arguments['date'])),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }
}
