<?php

namespace App\Console\Commands;

use App\Services\ZktecoTimekeepingService;
use Illuminate\Console\Command;

/**
 * Refresh the cached "Active Employees" map used to gate the SharePoint
 * Timekeeping mirror. Run on a schedule so workbook changes (new hires,
 * departures, corrected biometric ids) propagate predictably.
 */
class RefreshActiveEmployees extends Command
{
    protected $signature = 'zkteco:refresh-employees';

    protected $description = 'Rebuild the cached Active Employees map for the SharePoint Timekeeping mirror';

    public function handle(ZktecoTimekeepingService $service): int
    {
        if (! config('services.sharepoint.timekeeping_enabled')) {
            $this->warn('SharePoint Timekeeping mirror is disabled; nothing to refresh.');

            return self::SUCCESS;
        }

        $count = $service->refreshActiveEmployees();

        if ($count === 0) {
            $this->error('Active Employees map is empty. Check SharePoint credentials, the workbook, and its columns.');

            return self::FAILURE;
        }

        $this->info("Active Employees map refreshed: {$count} employee(s).");

        return self::SUCCESS;
    }
}
