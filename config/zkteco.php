<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Official scanner serial number
    |--------------------------------------------------------------------------
    |
    | When set, only push records originating from this device's serial number
    | are synced into attendance_logs. Raw scans from every device are still
    | stored in zkteco_attendances for auditing. Leave null to sync scans from
    | any device that checks in.
    |
    */

    'scanner_sn' => env('ZKTECO_SCANNER_SN'),

    /*
    |--------------------------------------------------------------------------
    | Double-punch dedupe window (minutes)
    |--------------------------------------------------------------------------
    |
    | A scan that lands within this many minutes of an existing attendance log
    | for the same employee is treated as an accidental double-punch and is not
    | recorded again. Falls back to the value configured here when the
    | GeneralSettings value is unavailable.
    |
    */

    'dedupe_minutes' => (int) env('ZKTECO_DEDUPE_MINUTES', 3),

    /*
    |--------------------------------------------------------------------------
    | Device handshake options
    |--------------------------------------------------------------------------
    |
    | Returned to the device on its GET /iclock/cdata handshake. These tune how
    | often the scanner pushes data and how much it retains. Defaults match the
    | ZKTeco push (PUSH SDK) protocol expectations.
    |
    */

    'options' => [
        'ErrorDelay' => 60,
        'Delay' => 30,
        'ResLogDay' => 18250,
        'ResLogDelCount' => 10000,
        'ResLogCount' => 50000,
        'TransTimes' => '00:00;14:05',
        'TransInterval' => 1,
        'TransFlag' => '1111000000',
        'Realtime' => 1,
        'Encrypt' => 0,
    ],

];
