<?php

use App\Console\Commands\AssignOnCall;
use App\Console\Commands\NotifyCelebrations;
use App\Console\Commands\NotifyOnCallStandIn;
use App\Console\Commands\RefreshActiveEmployees;
use App\Console\Commands\TriggerCelebrationsFlow;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune activity-log entries older than the configured retention (365 days).
Schedule::command('activitylog:clean')->daily();

// Announce today's birthdays and work anniversaries each morning.
Schedule::command(NotifyCelebrations::class)->dailyAt('08:00');

// Trigger the Teams celebrations flow at 9am (Asia/Manila), but only on days
// when someone actually has a birthday or work anniversary.
Schedule::command(TriggerCelebrationsFlow::class)->dailyAt('09:00');

// Refresh the Active Employees map that gates the SharePoint Timekeeping mirror.
Schedule::command(RefreshActiveEmployees::class)->dailyAt('05:00');

// Declare the on-call ("late dev") developer at the start of each week.
Schedule::command(AssignOnCall::class)->weeklyOn(1, '00:05');

// Each morning, tell the stand-in when the week's on-call dev is on leave today.
Schedule::command(NotifyOnCallStandIn::class)->dailyAt('07:30');

// Daily database backup at noon (Asia/Manila) to the configured disk, then
// prune backups older than the retention window in config/backup.php.
Schedule::command('backup:run --only-db')->dailyAt('12:00');
Schedule::command('backup:clean')->dailyAt('12:30');
