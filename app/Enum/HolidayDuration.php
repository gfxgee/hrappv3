<?php

namespace App\Enum;

enum HolidayDuration: string
{
    case FULL_DAY = 'full_day';
    case FIRST_HALF = 'first_half';
    case SECOND_HALF = 'second_half';

    public function label(): string
    {
        return match ($this) {
            self::FULL_DAY => 'Full day',
            self::FIRST_HALF => 'First half',
            self::SECOND_HALF => 'Second half',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $duration): array => [$duration->value => $duration->label()])
            ->all();
    }
}
