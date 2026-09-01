<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Company-wide, UI-configurable business rules. Edited from the admin
 * "Settings" page and consumed by the DTR, leave, dashboard, biometric sync,
 * and praise features. Defaults are seeded by the settings migration.
 */
class GeneralSettings extends Settings
{
    /*
    |--------------------------------------------------------------------------
    | Attendance / DTR
    |--------------------------------------------------------------------------
    */

    /** Hours deducted for lunch once the worked span exceeds the threshold. */
    public float $lunchHours;

    /** Gross worked hours above which the lunch break is deducted. */
    public float $lunchThresholdHours;

    /** Maximum overtime hours an employee may file per overtime request. */
    public float $maxOvertimeHours;

    /** Standard paid hours in a full working day (used for leave credits). */
    public float $standardWorkingHours;

    /**
     * ISO-8601 weekday numbers that count as working days (1 = Mon … 7 = Sun).
     *
     * @var array<int, int>
     */
    public array $workingDays;

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    /** Days ahead the employee dashboard's "Coming up" widget looks. */
    public int $comingUpWindowDays;

    /*
    |--------------------------------------------------------------------------
    | Biometric sync
    |--------------------------------------------------------------------------
    */

    /**
     * Minutes an employee may clock in past their scheduled start without being
     * marked late on the DTR.
     */
    public int $lateGraceMinutes;

    /** Window for collapsing accidental double-punches from the scanner. */
    public int $biometricDedupeMinutes;

    /*
    |--------------------------------------------------------------------------
    | Recognition
    |--------------------------------------------------------------------------
    */

    /** GIFs fetched per page in the praise comment picker. */
    public int $praiseGifPerPage;

    public static function group(): string
    {
        return 'general';
    }
}
