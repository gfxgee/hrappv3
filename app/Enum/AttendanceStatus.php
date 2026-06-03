<?php

namespace App\Enum;

use JsonSerializable;

enum AttendanceStatus : string implements JsonSerializable
{
    case FOR_APPROVAL = 'forapproval';
    case APPROVED = 'approved';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case APPROVED_AND_VERIFIED = 'verified';

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
    public function label(): string 
    {
        return match($this) {
            self::FOR_APPROVAL => 'For Approval',
            self::APPROVED => 'Approved',
            self::CANCELLED => 'Cancelled',
            self::REJECTED => 'Rejected',
            self::APPROVED_AND_VERIFIED => 'Verified',
        };
    }

    public function color(): string 
    {
        return match($this) {
            self::FOR_APPROVAL => 'info',
            self::APPROVED => 'success',
            self::CANCELLED => 'warning',
            self::REJECTED => 'danger',
            self::APPROVED_AND_VERIFIED => 'primary',
        };
    }

    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }
        return $array;
    }
}
