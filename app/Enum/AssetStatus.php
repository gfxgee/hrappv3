<?php

namespace App\Enum;

use JsonSerializable;

enum AssetStatus: string implements JsonSerializable
{
    case AVAILABLE = 'available';
    case ASSIGNED = 'assigned';
    case BORROWED = 'borrowed';
    case MAINTENANCE = 'maintenance';
    case RETIRED = 'retired';

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::ASSIGNED => 'Assigned',
            self::BORROWED => 'Borrowed',
            self::MAINTENANCE => 'Maintenance',
            self::RETIRED => 'Retired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => 'success',
            self::ASSIGNED => 'info',
            self::BORROWED => 'warning',
            self::MAINTENANCE => 'gray',
            self::RETIRED => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }

        return $array;
    }
}
