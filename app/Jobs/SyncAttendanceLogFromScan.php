<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\ZktecoAttendance;
use App\Settings\GeneralSettings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Translate one raw biometric scan into an attendance log.
 *
 * The scan's enrolled user id is matched to {@see User::$bio_metric_id}. The
 * punch type is inferred by an open-shift toggle (matching the on-screen
 * clock-in/out widget): a scan that lands while a shift is open becomes a
 * clock-out, otherwise it opens a new shift as a clock-in. Accidental
 * double-punches within the configured window are dropped.
 */
class SyncAttendanceLogFromScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * How far back a clock-in is still considered an open shift. Covers night
     * shifts that span midnight plus overtime, matching the clock-in widget.
     */
    private const ACTIVE_SHIFT_LOOKBACK_HOURS = 24;

    public const DEVICE = 'biometric';

    public function __construct(public ZktecoAttendance $attendance) {}

    public function handle(): void
    {
        $allowedSn = config('zkteco.scanner_sn');

        if (filled($allowedSn) && $this->attendance->sn !== $allowedSn) {
            Log::info('ZKTeco: skipping sync — scan not from the official scanner', [
                'sn' => $this->attendance->sn,
                'allowed_sn' => $allowedSn,
            ]);

            return;
        }

        $user = User::query()
            ->where('bio_metric_id', $this->attendance->bio_metric_id)
            ->first();

        if ($user === null) {
            Log::warning('ZKTeco: no employee matched the biometric id, skipping sync', [
                'bio_metric_id' => $this->attendance->bio_metric_id,
                'sn' => $this->attendance->sn,
            ]);

            return;
        }

        $scannedAt = $this->attendance->scanned_at;

        if ($this->isDoublePunch($user->id, $scannedAt)) {
            Log::info('ZKTeco: dropping double-punch', [
                'user_id' => $user->id,
                'scanned_at' => $scannedAt->toDateTimeString(),
            ]);

            return;
        }

        AttendanceLog::create([
            'user_id' => $user->id,
            'type' => $this->resolveType($user->id, $scannedAt),
            'device' => self::DEVICE,
            'remarks' => 'Recorded from biometric scanner',
            'created_at' => $scannedAt,
            'updated_at' => $scannedAt,
        ]);
    }

    /**
     * Whether another attendance log already exists for this user within the
     * dedupe window around the scan — i.e. an accidental repeated scan.
     */
    protected function isDoublePunch(int $userId, CarbonInterface $scannedAt): bool
    {
        $minutes = $this->dedupeMinutes();

        if ($minutes <= 0) {
            return false;
        }

        return AttendanceLog::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [
                $scannedAt->copy()->subMinutes($minutes),
                $scannedAt->copy()->addMinutes($minutes),
            ])
            ->exists();
    }

    /**
     * Resolve the punch type by open-shift toggle: clock-out when a shift is
     * open (a clock-in within the lookback window without a later clock-out),
     * otherwise clock-in.
     */
    protected function resolveType(int $userId, CarbonInterface $scannedAt): string
    {
        $openClockIn = AttendanceLog::query()
            ->where('user_id', $userId)
            ->where('type', 'clockin')
            ->where('created_at', '>=', $scannedAt->copy()->subHours(self::ACTIVE_SHIFT_LOOKBACK_HOURS))
            ->where('created_at', '<=', $scannedAt)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($openClockIn === null) {
            return 'clockin';
        }

        $hasClockOut = AttendanceLog::query()
            ->where('user_id', $userId)
            ->where('type', 'clockout')
            ->where('id', '>', $openClockIn->id)
            ->exists();

        return $hasClockOut ? 'clockin' : 'clockout';
    }

    protected function dedupeMinutes(): int
    {
        try {
            return app(GeneralSettings::class)->biometricDedupeMinutes;
        } catch (\Throwable) {
            return (int) config('zkteco.dedupe_minutes', 3);
        }
    }
}
