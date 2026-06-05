<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class UpcomingBirthdaysWidget extends Widget
{
    protected string $view = 'filament.widgets.upcoming-birthdays-widget';

    /** One-third width on desktop (full on mobile); sits beside Time Tracking. */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 1];

    protected static ?int $sort = -2;

    /** How far ahead to look for birthdays. */
    public const WINDOW_DAYS = 45;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Active employees whose birthday falls within the upcoming window,
     * soonest first.
     *
     * @return Collection<int, array{user: User, date: Carbon, days: int, isToday: bool}>
     */
    public function birthdays(): Collection
    {
        $today = today();

        return User::query()
            ->active()
            ->whereNotNull('birthday')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($today): ?array {
                try {
                    $birthday = Carbon::parse($user->birthday);
                } catch (Throwable) {
                    return null;
                }

                // This year's occurrence (clamped for short months, e.g. Feb 29).
                $monthAnchor = $today->copy()->month($birthday->month);
                $next = $monthAnchor->day(min($birthday->day, $monthAnchor->daysInMonth));

                if ($next->lessThan($today)) {
                    $next = $next->addYear();
                }

                $days = (int) $today->diffInDays($next);

                return [
                    'user' => $user,
                    'date' => $next,
                    'days' => $days,
                    'isToday' => $days === 0,
                ];
            })
            ->filter()
            ->filter(fn (array $row): bool => $row['days'] <= self::WINDOW_DAYS)
            ->sortBy('days')
            ->take(6)
            ->values();
    }
}
