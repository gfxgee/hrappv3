<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\ZktecoAttendance;
use App\Settings\GeneralSettings;
use Illuminate\Support\Carbon;

/**
 * Records a single biometric punch (from the SharePoint/Power Automate webhook)
 * into attendance_logs. Employees are matched by email, punches are deduped by
 * their source id, and a generic "SCAN" is resolved to a clock-in or clock-out
 * based on whether the employee already has an open shift that day.
 */
class AttendancePunchService
{
    public const DEVICE = 'biometric';

    /**
     * How far back an open clock-in stays "current" when auto-resolving a SCAN.
     * Covers a night shift plus slack so a morning scan closes the prior
     * evening's clock-in, without pairing across a long-stale missed punch.
     */
    public const OPEN_SHIFT_LOOKBACK_HOURS = 18;

    /**
     * @param  array{external_id: string, source_id?: string, title: string, email: string, punched_at: Carbon}  $punch
     * @return array{status: 'created'|'duplicate'|'mirrored'|'unmatched', type?: string, attendance_log_id?: int}
     */
    public function record(array $punch): array
    {
        // Idempotent: a re-delivered or "modified" SharePoint item never logs twice.
        if (AttendanceLog::query()->where('external_id', $punch['external_id'])->exists()) {
            return ['status' => 'duplicate'];
        }

        // Our own mirror coming back: MirrorPunchToTimekeeping created this
        // SharePoint item from a scan the scanner already pushed to us, and
        // SyncAttendanceLogFromScan logged it at its true scan time. Re-logging
        // it here would duplicate the punch — and at the wrong time, since the
        // item carries the mirror's clock, not the scanner's. Items created in
        // SharePoint by anything else still ingest normally.
        if ($this->isOwnMirror($punch['source_id'] ?? null)) {
            return ['status' => 'mirrored'];
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($punch['email'])])->first();

        if ($user === null) {
            return ['status' => 'unmatched'];
        }

        $type = $this->resolveType($punch['title'], $user->id, $punch['punched_at']);

        // Cross-source dedup: the same scan often arrives directly from the
        // scanner (SyncAttendanceLogFromScan) seconds before this SharePoint
        // round-trip comes back. If an equivalent punch (same employee, same
        // type) was already logged within the dedupe window, skip this one.
        if ($this->alreadyLogged($user->id, $type, $punch['punched_at'])) {
            return ['status' => 'duplicate'];
        }

        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'device' => self::DEVICE,
            'external_id' => $punch['external_id'],
            'remarks' => 'Biometric punch ('.$punch['title'].')',
            'created_at' => $punch['punched_at'],
            'updated_at' => $punch['punched_at'],
        ]);

        return ['status' => 'created', 'type' => $type, 'attendance_log_id' => $log->id];
    }

    /**
     * Map the punch title to a clock-in/clock-out. Explicit TIME-IN / TIME-OUT
     * map directly; a generic SCAN becomes a clock-out when the employee's last
     * punch that day was a clock-in, otherwise a clock-in.
     */
    protected function resolveType(string $title, int $userId, Carbon $punchedAt): string
    {
        return match (mb_strtoupper(trim($title))) {
            'TIME-IN' => 'clockin',
            'TIME-OUT' => 'clockout',
            default => $this->autoType($userId, $punchedAt),
        };
    }

    /**
     * Whether this SharePoint item is one we created ourselves by mirroring a
     * scan into the Timekeeping list. Unlike the time-window dedup below, this
     * holds however long the round-trip took — including a scanner that
     * buffered a day of punches offline and pushed them all at once.
     */
    protected function isOwnMirror(?string $sourceId): bool
    {
        if ($sourceId === null || $sourceId === '') {
            return false;
        }

        return ZktecoAttendance::query()
            ->where('timekeeping_item_id', $sourceId)
            ->exists();
    }

    /**
     * Whether an equivalent punch (same employee, same type) already exists
     * within the dedupe window — covering both the direct-scanner round-trip
     * and accidental repeated scans.
     */
    protected function alreadyLogged(int $userId, string $type, Carbon $punchedAt): bool
    {
        $minutes = $this->dedupeMinutes();

        if ($minutes <= 0) {
            return false;
        }

        return AttendanceLog::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereBetween('created_at', [
                $punchedAt->copy()->subMinutes($minutes),
                $punchedAt->copy()->addMinutes($minutes),
            ])
            ->exists();
    }

    protected function dedupeMinutes(): int
    {
        try {
            return app(GeneralSettings::class)->biometricDedupeMinutes;
        } catch (\Throwable) {
            return (int) config('zkteco.dedupe_minutes', 3);
        }
    }

    protected function autoType(int $userId, Carbon $punchedAt): string
    {
        // Look back over a window rather than the calendar day so an
        // early-morning scan closes a clock-in from the previous evening
        // (night shift) instead of wrongly opening a new one.
        $lastPunch = AttendanceLog::query()
            ->where('user_id', $userId)
            ->where('created_at', '<=', $punchedAt)
            ->where('created_at', '>=', $punchedAt->copy()->subHours(self::OPEN_SHIFT_LOOKBACK_HOURS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('type');

        return $lastPunch === 'clockin' ? 'clockout' : 'clockin';
    }
}
