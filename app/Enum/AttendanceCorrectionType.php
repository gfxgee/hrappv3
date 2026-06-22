<?php

namespace App\Enum;

enum AttendanceCorrectionType: string
{
    case MISSING_CLOCK_IN = 'missing_clockin';
    case MISSING_CLOCK_OUT = 'missing_clockout';
    case WRONG_TIME = 'wrong_time';
    case OTHER = 'other';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::MISSING_CLOCK_IN => 'Missing clock-in',
            self::MISSING_CLOCK_OUT => 'Missing clock-out',
            self::WRONG_TIME => 'Incorrect time',
            self::OTHER => 'Other',
        };
    }

    /**
     * Value => label map for select inputs and filters.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
