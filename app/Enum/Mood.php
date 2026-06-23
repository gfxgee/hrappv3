<?php

namespace App\Enum;

enum Mood: string
{
    case HAPPY = 'happy';
    case CALM = 'calm';
    case STRESSED = 'stressed';
    case SICK = 'sick';
    case TIRED = 'tired';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::HAPPY => 'Happy',
            self::CALM => 'Calm',
            self::STRESSED => 'Stressed',
            self::SICK => 'Sick',
            self::TIRED => 'Tired',
        };
    }

    /**
     * Emoji shown on the picker and in reports.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::HAPPY => '😊',
            self::CALM => '😌',
            self::STRESSED => '😫',
            self::TIRED => '😴',
            self::SICK => '🤒'
        };
    }

    /**
     * Filament colour name for badges and stats.
     */
    public function color(): string
    {
        return match ($this) {
            self::HAPPY => 'success',
            self::CALM=> 'info',
            self::TIRED => 'warning',
            self::STRESSED => 'danger',
            self::SICK => 'gray',
        };
    }

    /**
     * Whether this mood signals someone who may need support — used to flag
     * wellbeing concerns for HR.
     */
    public function needsAttention(): bool
    {
        return match ($this) {
            self::CALM, self::STRESSED  => true, self::SICK => true,
            self::HAPPY, self::TIRED=> false,
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
