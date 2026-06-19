<?php

namespace App\Enum;

enum Mood: string
{
    case HAPPY = 'happy';
    case EXCITED = 'excited';
    case SAD = 'sad';
    case STRESSED = 'stressed';
    case SICK = 'sick';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::HAPPY => 'Happy',
            self::EXCITED => 'Excited',
            self::SAD => 'Sad',
            self::STRESSED => 'Stressed',
            self::SICK => 'Sick',
        };
    }

    /**
     * Emoji shown on the picker and in reports.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::HAPPY => '😊',
            self::EXCITED => '🤩',
            self::SAD => '😢',
            self::STRESSED => '😫',
            self::SICK => '🤒',
        };
    }

    /**
     * Filament colour name for badges and stats.
     */
    public function color(): string
    {
        return match ($this) {
            self::HAPPY => 'success',
            self::EXCITED => 'info',
            self::SAD => 'warning',
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
            self::SAD, self::STRESSED, self::SICK => true,
            self::HAPPY, self::EXCITED => false,
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
