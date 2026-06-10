<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ShowsHolidayDetails;
use App\Models\Holiday;
use App\Settings\GeneralSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingHolidaysWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ShowsHolidayDetails;

    protected string $view = 'filament.widgets.upcoming-holidays-widget';

    /** One-third width on desktop; sits below Upcoming Birthdays. */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 0;

    /** Default look-ahead window (days), seeded into settings. */
    public const WINDOW_DAYS = 90;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /** Configured look-ahead window in days. */
    public function windowDays(): int
    {
        return app(GeneralSettings::class)->holidayWindowDays;
    }

    /**
     * Holidays from today through the window, soonest first.
     *
     * @return Collection<int, array{id: int, name: string, emoji: ?string, date: Carbon, day: string, duration: string, days: int, isToday: bool}>
     */
    public function holidays(): Collection
    {
        $today = today();

        return Holiday::query()
            ->active()
            ->whereBetween('date', [
                $today->toDateString(),
                $today->copy()->addDays($this->windowDays())->toDateString(),
            ])
            ->orderBy('date')
            ->take(6)
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'emoji' => $holiday->emoji,
                'date' => $holiday->date,
                'day' => $holiday->date->format('l'),
                'duration' => $holiday->duration?->label() ?? 'Full day',
                'days' => (int) $today->diffInDays($holiday->date),
                'isToday' => $holiday->date->isSameDay($today),
            ]);
    }
}
