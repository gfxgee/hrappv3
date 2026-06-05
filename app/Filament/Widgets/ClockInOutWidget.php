<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceLog;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ClockInOutWidget extends Widget
{
    protected string $view = 'filament.widgets.clock-in-out-widget';

    /**
     * Two-thirds width on desktop (full on mobile); rendered first.
     */
    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2];

    protected static ?int $sort = -3;

    /**
     * How far back to consider a clock-in as "still active" if not yet
     * clocked out. Covers night shifts that span midnight plus overtime.
     */
    private const ACTIVE_SHIFT_LOOKBACK_HOURS = 24;

    /**
     * Hidden for unauthenticated users (defensive — the panel auth middleware
     * already gates the dashboard).
     */
    public static function canView(): bool
    {
        return auth()->check();
    }

    public function clockIn(): void
    {
        // Block only when there's an OPEN shift (clocked in, not yet out).
        // After a completed shift, the user can clock in again for the next one.
        if ($this->getClockInLog() !== null && $this->getClockOutLog() === null) {
            return;
        }

        AttendanceLog::create([
            'user_id' => auth()->id(),
            'type' => 'clockin',
            'device' => 'web',
        ]);

        Notification::make()
            ->success()
            ->title('Clocked in')
            ->body('Have a productive day!')
            ->send();
    }

    public function clockOut(): void
    {
        if ($this->getClockInLog() === null || $this->getClockOutLog() !== null) {
            return; // no open shift to close
        }

        AttendanceLog::create([
            'user_id' => auth()->id(),
            'type' => 'clockout',
            'device' => 'web',
        ]);

        Notification::make()
            ->success()
            ->title('Clocked out')
            ->body('Have a great rest of your day!')
            ->send();
    }

    /**
     * The clock-in for the current shift — the most recent clock-in within the
     * lookback window. Returns null when there's no recent clock-in.
     */
    public function getClockInLog(): ?AttendanceLog
    {
        return AttendanceLog::query()
            ->where('user_id', auth()->id())
            ->where('type', 'clockin')
            ->where('created_at', '>=', now()->subHours(self::ACTIVE_SHIFT_LOOKBACK_HOURS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The clock-out for the current shift — a clock-out inserted AFTER the
     * active clock-in. Returns null while the shift is still open.
     *
     * Uses `id > $in->id` rather than a created_at comparison because the
     * default timestamp casts to seconds, so back-to-back writes (especially
     * in tests) can produce identical created_at values. Auto-increment IDs
     * are strictly monotonic, so "inserted after" is unambiguous.
     */
    public function getClockOutLog(): ?AttendanceLog
    {
        $in = $this->getClockInLog();

        if ($in === null) {
            return null;
        }

        return AttendanceLog::query()
            ->where('user_id', auth()->id())
            ->where('type', 'clockout')
            ->where('id', '>', $in->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Total seconds between this shift's clock-in and clock-out (or now if still open).
     */
    public function getElapsedSeconds(): ?int
    {
        $in = $this->getClockInLog();

        if ($in === null) {
            return null;
        }

        $end = $this->getClockOutLog()?->created_at ?? now();

        return (int) max(0, $in->created_at->diffInSeconds($end));
    }

    public function getElapsedHuman(): ?string
    {
        $seconds = $this->getElapsedSeconds();

        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    /**
     * @return 'not_started'|'in_progress'|'done'
     */
    public function getStatus(): string
    {
        return match (true) {
            $this->getClockInLog() === null => 'not_started',
            $this->getClockOutLog() === null => 'in_progress',
            default => 'done',
        };
    }

    /**
     * Format a clock-in/out time. Shows the day prefix only when the
     * timestamp is not today (e.g. a night-shift clock-in from yesterday).
     */
    public function formatLogTime(?AttendanceLog $log): string
    {
        if ($log === null) {
            return '—';
        }

        return $log->created_at->isToday()
            ? $log->created_at->format('h:i A')
            : $log->created_at->format('D h:i A');
    }
}
