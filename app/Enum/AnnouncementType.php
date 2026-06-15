<?php

namespace App\Enum;

enum AnnouncementType: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case SUCCESS = 'success';
    case DANGER = 'danger';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Info',
            self::WARNING => 'Warning',
            self::SUCCESS => 'Success',
            self::DANGER => 'Important',
        };
    }

    /**
     * Filament colour name for badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::SUCCESS => 'success',
            self::DANGER => 'danger',
        };
    }

    /**
     * Heroicon shown on the banner.
     */
    public function icon(): string
    {
        return match ($this) {
            self::INFO => 'heroicon-o-information-circle',
            self::WARNING => 'heroicon-o-exclamation-triangle',
            self::SUCCESS => 'heroicon-o-check-circle',
            self::DANGER => 'heroicon-o-exclamation-circle',
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
