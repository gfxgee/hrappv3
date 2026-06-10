<?php

namespace App\Filament\Widgets\Employee;

use App\Filament\Widgets\Concerns\ShowsHolidayDetails;
use App\Models\Holiday;
use App\Models\User;
use App\Settings\GeneralSettings;
use Carbon\CarbonInterface;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Merged upcoming events — birthdays, work anniversaries, and holidays —
 * within a configurable window. Holiday rows open the holiday-details modal.
 */
class ComingUpWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ShowsHolidayDetails;

    protected string $view = 'filament.widgets.employee.coming-up-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 1;

    /**
     * Days ahead to look. Defaults to the configurable "Coming up window"
     * setting; the HR Overview overrides it with a fixed 7-day week view.
     */
    public ?int $windowDays = null;

    /** Max entries shown. */
    public const LIMIT = 8;

    public function mount(): void
    {
        $this->windowDays ??= app(GeneralSettings::class)->comingUpWindowDays;
    }

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Upcoming entries within the window, soonest first.
     *
     * @return Collection<int, array{type: string, emoji: string, label: string, sub: ?string, date: CarbonInterface, days: int, isToday: bool, holidayId: ?int}>
     */
    public function entries(): Collection
    {
        $employees = User::query()->active()->get(['id', 'name', 'birthday', 'date_hired']);

        return $this->birthdayEntries($employees)
            ->concat($this->anniversaryEntries($employees))
            ->concat($this->holidayEntries())
            ->filter(fn (array $entry): bool => $entry['days'] <= $this->windowDays)
            ->sortBy('days')
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return Collection<int, array{type: string, emoji: string, label: string, sub: ?string, date: CarbonInterface, days: int, isToday: bool, holidayId: ?int}>
     */
    protected function birthdayEntries(Collection $employees): Collection
    {
        return $employees
            ->map(function (User $user): ?array {
                $next = $this->nextOccurrence($user->birthday);

                if ($next === null) {
                    return null;
                }

                $days = (int) today()->diffInDays($next);

                return [
                    'type' => 'birthday',
                    'emoji' => '🎂',
                    'label' => $user->name."'s birthday",
                    'sub' => null,
                    'date' => $next,
                    'days' => $days,
                    'isToday' => $days === 0,
                    'holidayId' => null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, User>  $employees
     * @return Collection<int, array{type: string, emoji: string, label: string, sub: ?string, date: CarbonInterface, days: int, isToday: bool, holidayId: ?int}>
     */
    protected function anniversaryEntries(Collection $employees): Collection
    {
        return $employees
            ->map(function (User $user): ?array {
                $next = $this->nextOccurrence($user->date_hired);

                if ($next === null) {
                    return null;
                }

                $years = $next->year - Carbon::parse($user->date_hired)->year;

                if ($years < 1) {
                    return null; // hired this year — no anniversary yet
                }

                $days = (int) today()->diffInDays($next);

                return [
                    'type' => 'anniversary',
                    'emoji' => '🎉',
                    'label' => $user->name." — {$years} ".($years === 1 ? 'yr' : 'yrs'),
                    'sub' => 'Work anniversary',
                    'date' => $next,
                    'days' => $days,
                    'isToday' => $days === 0,
                    'holidayId' => null,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{type: string, emoji: string, label: string, sub: ?string, date: CarbonInterface, days: int, isToday: bool, holidayId: ?int}>
     */
    protected function holidayEntries(): Collection
    {
        return Holiday::query()
            ->active()
            ->whereBetween('date', [today()->toDateString(), today()->addDays($this->windowDays)->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(function (Holiday $holiday): array {
                $days = (int) today()->diffInDays($holiday->date);

                return [
                    'type' => 'holiday',
                    'emoji' => $holiday->emoji ?: '📅',
                    'label' => $holiday->name,
                    'sub' => $holiday->duration?->label() ?? 'Full day',
                    'date' => $holiday->date,
                    'days' => $days,
                    'isToday' => $days === 0,
                    'holidayId' => $holiday->id,
                ];
            });
    }

    /**
     * This year's (or next year's) occurrence of an annual date, clamped for
     * short months (e.g. Feb 29). Null for blank/unparseable values — the
     * column is an uncast string.
     */
    protected function nextOccurrence(?string $date): ?CarbonInterface
    {
        if (blank($date)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }

        $monthAnchor = today()->month($parsed->month);
        $next = $monthAnchor->day(min($parsed->day, $monthAnchor->daysInMonth));

        if ($next->lessThan(today())) {
            $next = $next->addYear();
        }

        return $next;
    }
}
