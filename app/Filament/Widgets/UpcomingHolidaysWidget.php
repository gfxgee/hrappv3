<?php

namespace App\Filament\Widgets;

use App\Models\Holiday;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UpcomingHolidaysWidget extends Widget
{
    protected string $view = 'filament.widgets.upcoming-holidays-widget';

    /** One-third width on desktop; sits below Upcoming Birthdays. */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = 0;

    /** How far ahead to look for holidays. */
    public const WINDOW_DAYS = 90;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Holidays from today through the window, soonest first.
     *
     * @return Collection<int, array{name: string, date: Carbon, days: int, isToday: bool}>
     */
    public function holidays(): Collection
    {
        $today = today();

        return Holiday::query()
            ->whereBetween('date', [
                $today->toDateString(),
                $today->copy()->addDays(self::WINDOW_DAYS)->toDateString(),
            ])
            ->orderBy('date')
            ->take(6)
            ->get()
            ->map(fn (Holiday $holiday): array => [
                'name' => $holiday->name,
                'date' => $holiday->date,
                'days' => (int) $today->diffInDays($holiday->date),
                'isToday' => $holiday->date->isSameDay($today),
            ]);
    }
}
