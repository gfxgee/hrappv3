<?php

namespace App\Jobs;

use App\Models\User;
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
 * whether the employee toggled in/out. The employee's email is resolved locally
 * from users.bio_metric_id.
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

        $email = User::query()
            ->where('bio_metric_id', $this->attendance->bio_metric_id)
            ->value('email');

        if (blank($email)) {
            Log::warning('ZKTeco: no employee email for SharePoint mirror, skipping', [
                'bio_metric_id' => $this->attendance->bio_metric_id,
            ]);

            return;
        }

        $service->recordPunch($email, $this->attendance);
    }
}
