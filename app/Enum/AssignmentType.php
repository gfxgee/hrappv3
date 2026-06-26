<?php

namespace App\Enum;

use JsonSerializable;

enum AssignmentType: string implements JsonSerializable
{
    case PERMANENT = 'permanent';
    case BORROW = 'borrow';

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::PERMANENT => 'Permanent',
            self::BORROW => 'Borrow',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PERMANENT => 'info',
            self::BORROW => 'warning',
        };
    }

    /**
     * The asset status that results from this kind of assignment.
     */
    public function resultingAssetStatus(): AssetStatus
    {
        return match ($this) {
            self::PERMANENT => AssetStatus::ASSIGNED,
            self::BORROW => AssetStatus::BORROWED,
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
