<?php

namespace App\Enum;

use JsonSerializable;

enum AssetCategory: string implements JsonSerializable
{
    case SYSTEM_UNIT = 'system_unit';
    case PROCESSOR = 'processor';
    case MOTHERBOARD = 'motherboard';
    case RAM = 'ram';
    case STORAGE = 'storage';
    case GPU = 'gpu';
    case MONITOR = 'monitor';
    case KEYBOARD = 'keyboard';
    case MOUSE = 'mouse';
    case HEADSET = 'headset';
    case WEBCAM = 'webcam';
    case UPS = 'ups';
    case LAPTOP = 'laptop';
    case OTHER = 'other';

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_UNIT => 'System Unit / CPU',
            self::PROCESSOR => 'Processor',
            self::MOTHERBOARD => 'Motherboard',
            self::RAM => 'RAM',
            self::STORAGE => 'Storage (SSD/HDD)',
            self::GPU => 'GPU / Video Card',
            self::MONITOR => 'Monitor',
            self::KEYBOARD => 'Keyboard',
            self::MOUSE => 'Mouse',
            self::HEADSET => 'Headset',
            self::WEBCAM => 'Webcam',
            self::UPS => 'UPS',
            self::LAPTOP => 'Laptop',
            self::OTHER => 'Other',
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
