<?php

namespace App\Jobs;

use App\Models\ZktecoAttendance;
use App\Services\ZktecoTimekeepingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Mirror one biometric scan into the SharePoint "Timekeeping" list, in addition
 * to the local attendance_logs write handled by {@see SyncAttendanceLogFromScan}.
 *
 * This replicates the DF Portal's behaviour: every scan from the official
 * scanner is recorded, labelled by the device's status byte, regardless of
 * whether the employee toggled in/out. The authority for who may appear in
 * Timekeeping (and which email to use) is the "Active Employees" workbook — a
 * biometric id not listed there is not a legitimate active employee and is
 * skipped.
 */
class MirrorPunchToTimekeeping implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public ZktecoAttendance $attendance) {}

    public function handle(ZktecoTimekeepingService $service): void
    {
        if (! config('services.sharepoint.timekeeping_enabled')) {
            return;
        }

        $allowedSn = config('zkteco.scanner_sn');

        if (filled($allowedSn) && $this->attendance->sn !== $allowedSn) {
            return;
        }

        $employee = $service->findEmployeeByBiometricId($this->attendance->bio_metric_id);

        if ($employee === null) {
            Log::warning('ZKTeco: biometric id not in Active Employees, skipping Timekeeping mirror', [
                'bio_metric_id' => $this->attendance->bio_metric_id,
            ]);

            return;
        }

        $service->recordPunch($employee['email'], $this->attendance);
    }
}
