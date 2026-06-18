<?php

namespace App\Enum;

enum SuggestionStatus: string
{
    case NEW = 'new';
    case REVIEWED = 'reviewed';
    case ACTIONED = 'actioned';
    case DISMISSED = 'dismissed';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::REVIEWED => 'Reviewed',
            self::ACTIONED => 'Actioned',
            self::DISMISSED => 'Dismissed',
        };
    }

    /**
     * Filament colour name for badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::NEW => 'info',
            self::REVIEWED => 'warning',
            self::ACTIONED => 'success',
            self::DISMISSED => 'gray',
        };
    }

    /**
     * Value => label map for select inputs.
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
