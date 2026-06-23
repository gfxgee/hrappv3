<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
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
     * @param  array{external_id: string, title: string, email: string, punched_at: Carbon}  $punch
     * @return array{status: 'created'|'duplicate'|'unmatched', type?: string, attendance_log_id?: int}
     */
    public function record(array $punch): array
    {
        // Idempotent: a re-delivered or "modified" SharePoint item never logs twice.
        if (AttendanceLog::query()->where('external_id', $punch['external_id'])->exists()) {
            return ['status' => 'duplicate'];
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($punch['email'])])->first();

        if ($user === null) {
            return ['status' => 'unmatched'];
        }

        $type = $this->resolveType($punch['title'], $user->id, $punch['punched_at']);

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

    protected function autoType(int $userId, Carbon $punchedAt): string
    {
        $lastPunchToday = AttendanceLog::query()
            ->where('user_id', $userId)
            ->whereDate('created_at', $punchedAt->toDateString())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('type');

        return $lastPunchToday === 'clockin' ? 'clockout' : 'clockin';
    }
}
