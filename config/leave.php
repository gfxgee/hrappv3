<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Working days
    |--------------------------------------------------------------------------
    |
    | ISO-8601 day-of-week numbers that count as working days when deducting
    | leave credits: 1 = Monday ... 7 = Sunday. Days not listed (by default
    | Saturday (6) and Sunday (7)) are skipped, along with any dates in the
    | holidays table. Adjust this if your organisation works weekends.
    |
    */

    'working_days' => [1, 2, 3, 4, 5],

];
