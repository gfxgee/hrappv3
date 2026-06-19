<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Resolves who is celebrating a birthday or work anniversary, used by the
 * "Coming up" widget, the daily celebrations notification, and the celebrant's
 * dashboard greeting. Birthday/hire dates are stored as uncast strings, so all
 * parsing is defensive.
 */
class CelebrationService
{
    /**
     * Active employees whose birthday falls today.
     *
     * @return Collection<int, User>
     */
    public function birthdaysToday(): Collection
    {
        return $this->activeEmployees()
            ->filter(fn (User $user): bool => self::nextAnnualOccurrence($user->birthday)?->isToday() ?? false)
            ->values();
    }

    /**
     * Active employees marking a work anniversary today, with their years of
     * service (employees hired today, i.e. 0 years, are excluded).
     *
     * @return Collection<int, array{user: User, years: int}>
     */
    public function anniversariesToday(): Collection
    {
        return $this->activeEmployees()
            ->map(function (User $user): ?array {
                $next = self::nextAnnualOccurrence($user->date_hired);

                if ($next === null || ! $next->isToday()) {
                    return null;
                }

                $years = today()->year - Carbon::parse($user->date_hired)->year;

                return $years >= 1 ? ['user' => $user, 'years' => $years] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Today's celebration for a single employee, if any — for their own
     * dashboard greeting.
     *
     * @return array{type: string, emoji: string, message: string}|null
     */
    public function celebrationFor(User $user): ?array
    {
        if (self::nextAnnualOccurrence($user->birthday)?->isToday()) {
            return [
                'type' => 'birthday',
                'emoji' => '🎂',
                'message' => 'Happy Birthday! Wishing you a wonderful day. 🎉',
            ];
        }

        $next = self::nextAnnualOccurrence($user->date_hired);

        if ($next?->isToday()) {
            $years = today()->year - Carbon::parse($user->date_hired)->year;

            if ($years >= 1) {
                return [
                    'type' => 'anniversary',
                    'emoji' => '🎉',
                    'message' => sprintf(
                        'Happy work anniversary! Thank you for %d %s with us. 🙌',
                        $years,
                        $years === 1 ? 'year' : 'years',
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * This year's (or next year's) occurrence of an annual date, clamped for
     * short months (e.g. Feb 29 → Feb 28). Null for blank/unparseable values.
     */
    public static function nextAnnualOccurrence(?string $date): ?CarbonInterface
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

    /**
     * @return Collection<int, User>
     */
    protected function activeEmployees(): Collection
    {
        return User::query()->active()->get(['id', 'name', 'birthday', 'date_hired']);
    }
}
