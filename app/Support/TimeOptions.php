<?php

namespace App\Support;

class TimeOptions
{
    /**
     * Quarter-hour times of day, formatted "HH:MM", from "00:00" through "24:00".
     * Suitable as Filament Select `options()` (key === label for consistent storage).
     *
     * @return array<string, string>
     */
    public static function quarterHours(): array
    {
        $options = [];

        for ($hour = 0; $hour <= 24; $hour++) {
            $maxMinute = $hour === 24 ? 0 : 45;

            for ($minute = 0; $minute <= $maxMinute; $minute += 15) {
                $key = sprintf('%02d:%02d', $hour, $minute);
                $options[$key] = $key;
            }
        }

        return $options;
    }

    /**
     * Parse an "HH:MM" string into minutes since midnight.
     * Returns null when the value is missing or malformed.
     */
    public static function toMinutes(?string $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):([0-5]\d)$/', $time, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 24 || ($hours === 24 && $minutes > 0)) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    /**
     * The number of hours between two "HH:MM" times.
     * Returns null when either time is invalid or the range is non-positive.
     */
    public static function durationHours(?string $start, ?string $end): ?float
    {
        $startMinutes = self::toMinutes($start);
        $endMinutes = self::toMinutes($end);

        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            return null;
        }

        return ($endMinutes - $startMinutes) / 60;
    }
}
