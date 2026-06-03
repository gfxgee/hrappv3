<?php

namespace App\Enum;

enum LeaveType: string
{
    case WFH = 'wfh';
    case VACATION = 'vacation';
    case SICK = 'sick';
    case EMERGENCY = 'emergency';
    case BEREAVEMENT = 'bereavement';
    case MATERNITY = 'maternity';
    case PATERNITY = 'paternity';
    case LWOP = 'lwop'; // Leave Without Pay
    case EMPTY = '';
    /**
     * Emoji shown alongside the leave type label in dropdowns, badges,
     * and credit cards. Edit these to change the icons across the app.
     */
    public function icon(): string
    {
        return match ($this) {
            self::WFH => '🏠',
            self::VACATION => '🏖️',
            self::SICK => '🤒',
            self::EMERGENCY => '🚨',
            self::BEREAVEMENT => '🕊️',
            self::MATERNITY => '🤰',
            self::PATERNITY => '👶',
            self::LWOP => '💼',
        };
    }

    /**
     * Plain text label, no emoji — suitable for non-UI usage like AI prompts.
     */
    public function plainLabel(): string
    {
        return match ($this) {
            self::WFH => 'Work from Home',
            self::VACATION => 'Vacation Leave',
            self::SICK => 'Sick Leave',
            self::EMERGENCY => 'Emergency Leave',
            self::BEREAVEMENT => 'Bereavement Leave',
            self::MATERNITY => 'Maternity Leave',
            self::PATERNITY => 'Paternity Leave',
            self::LWOP => 'Leave Without Pay',
        };
    }

    /**
     * Get the display name of the leave type, prefixed with its icon.
     */
    public function label(): string
    {
        return $this->icon().' '.$this->plainLabel();
    }

    /**
     * Get all leave types as an array.
     */
    public static function all(): array
    {
        return [
            self::WFH,
            self::VACATION,
            self::SICK,
            self::EMERGENCY,
            self::BEREAVEMENT,
            self::MATERNITY,
            self::PATERNITY,
            self::LWOP,
        ];
    }

    /**
     * Get a key-value list of leave types.
     */
    public static function toArray(): array
    {
        return array_map(fn($leave) => ['id' => $leave->value, 'name' => $leave->label()], self::all());
    }
}
