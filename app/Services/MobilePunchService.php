<?php

namespace App\Services;

use App\Filament\Widgets\ClockInOutWidget;
use App\Models\AttendanceLog;
use App\Models\User;

/**
 * Clock in/out for the employee mobile app. Mirrors the shift logic of
 * {@see ClockInOutWidget} but is user-scoped (not tied
 * to auth()) and tags punches with device 'mobile' so the source stays
 * distinguishable from the biometric scanner ('biometric') and web ('web').
 */
class MobilePunchService
{
    /**
     * How far back a clock-in is still considered an open shift. Covers night
     * shifts that span midnight plus overtime, matching the scanner sync.
     */
    private const ACTIVE_SHIFT_LOOKBACK_HOURS = 24;

    public const DEVICE = 'mobile';

    /**
     * Record the next punch for the user based on the current shift state:
     * clock in when no shift is open today, clock out when one is open, and a
     * no-op once the shift is already complete for the day.
     *
     * @return array{type: string, log: AttendanceLog}|null null when there is nothing valid to punch
     */
    public function punch(User $user): ?array
    {
        $type = match ($this->status($user)) {
            'not_started' => $this->canClockIn($user) ? 'clockin' : null,
            'in_progress' => 'clockout',
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'device' => self::DEVICE,
        ]);

        return ['type' => $type, 'log' => $log];
    }

    /**
     * A snapshot of the user's current shift for the mobile home screen.
     *
     * @return array{status: string, clock_in_at: ?string, clock_out_at: ?string, clock_in_iso: ?string, elapsed_human: ?string}
     */
    public function snapshot(User $user): array
    {
        $in = $this->currentClockIn($user);
        $out = $this->currentClockOut($user);

        return [
            'status' => $this->status($user),
            'clock_in_at' => $in?->created_at->format('h:i A'),
            'clock_out_at' => $out?->created_at->format('h:i A'),
            'clock_in_iso' => $in?->created_at->toIso8601String(),
            'elapsed_human' => $this->elapsedHuman($user),
        ];
    }

    /**
     * @return 'not_started'|'in_progress'|'done'
     */
    public function status(User $user): string
    {
        return match (true) {
            $this->currentClockIn($user) === null => 'not_started',
            $this->currentClockOut($user) === null => 'in_progress',
            default => 'done',
        };
    }

    /**
     * Whether the employee may start a shift now: not already in an open shift,
     * and hasn't already clocked in today (one scheduled shift per day).
     */
    public function canClockIn(User $user): bool
    {
        if ($this->currentClockIn($user) !== null && $this->currentClockOut($user) === null) {
            return false;
        }

        return ! $this->hasClockedInToday($user);
    }

    public function hasClockedInToday(User $user): bool
    {
        return AttendanceLog::query()
            ->where('user_id', $user->id)
            ->where('type', 'clockin')
            ->whereDate('created_at', today())
            ->exists();
    }

    /**
     * The clock-in for the current shift: an open shift always shows (so a night
     * shift spanning midnight stays visible), otherwise today's clock-in.
     */
    public function currentClockIn(User $user): ?AttendanceLog
    {
        $recent = AttendanceLog::query()
            ->where('user_id', $user->id)
            ->where('type', 'clockin')
            ->where('created_at', '>=', now()->subHours(self::ACTIVE_SHIFT_LOOKBACK_HOURS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($recent === null) {
            return null;
        }

        return ($this->clockOutAfter($recent) === null || $recent->created_at->isToday()) ? $recent : null;
    }

    /**
     * The clock-out that closes the current shift, or null while it is open.
     */
    public function currentClockOut(User $user): ?AttendanceLog
    {
        $in = $this->currentClockIn($user);

        return $in === null ? null : $this->clockOutAfter($in);
    }

    public function elapsedHuman(User $user): ?string
    {
        $in = $this->currentClockIn($user);

        if ($in === null) {
            return null;
        }

        $end = $this->currentClockOut($user)?->created_at ?? now();
        $seconds = (int) max(0, $in->created_at->diffInSeconds($end));

        return sprintf('%dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * The first clock-out at or after the given clock-in — the one that closes
     * it. Matched by time so an earlier orphan clock-out can't pair with a later
     * clock-in; the id only breaks ties within the same second.
     */
    protected function clockOutAfter(AttendanceLog $in): ?AttendanceLog
    {
        return AttendanceLog::query()
            ->where('user_id', $in->user_id)
            ->where('type', 'clockout')
            ->where(function ($query) use ($in): void {
                $query->where('created_at', '>', $in->created_at)
                    ->orWhere(function ($tie) use ($in): void {
                        $tie->where('created_at', $in->created_at)->where('id', '>', $in->id);
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
