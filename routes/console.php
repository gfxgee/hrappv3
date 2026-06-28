<?php

use App\Console\Commands\NotifyCelebrations;
use App\Console\Commands\RefreshActiveEmployees;
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

// Refresh the Active Employees map that gates the SharePoint Timekeeping mirror.
Schedule::command(RefreshActiveEmployees::class)->dailyAt('05:00');

// Nightly database backup to the configured disk (Google Drive in prod), then
// prune old backups per the retention policy in config/backup.php.
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run --only-db')->dailyAt('01:30');
