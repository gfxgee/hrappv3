<?php

namespace App\Filament\Widgets;

use App\Enum\AttendanceStatus;
use App\Filament\Pages\FileOverTimeRequest;
use App\Models\AttendanceLog;
use App\Models\OverTimeRequest;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

class ClockInOutWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

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
        // One shift per day: blocked while a shift is open, and once a shift has
        // already started today (no manual "start new shift").
        if (! $this->canClockIn()) {
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

    /**
     * Clock in for an overtime shift: the same punch as a normal clock-in, with
     * the overtime request filed in the same step so the employee doesn't have
     * to visit the Overtime Request Form separately.
     */
    public function otClockInAction(): Action
    {
        return Action::make('otClockIn')
            ->label('OT Clock In')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->modalHeading('Clock in for overtime')
            ->modalDescription('Files your overtime request and clocks you in.')
            ->modalSubmitActionLabel('File & clock in')
            ->schema(FileOverTimeRequest::overtimeHoursAndReasonFields())
            ->action(function (array $data): void {
                // Same one-shift-per-day rule as the normal clock-in.
                if (! $this->canClockIn()) {
                    return;
                }

                $request = $this->fileOvertime($data, today()->toDateString());

                AttendanceLog::create([
                    'user_id' => auth()->id(),
                    'type' => 'clockin',
                    'device' => 'web',
                    'remarks' => "OT shift — overtime request #{$request->id}",
                ]);

                Notification::make()
                    ->success()
                    ->title('Clocked in for overtime')
                    ->body('Your overtime request has been sent for approval.')
                    ->send();
            });
    }

    /**
     * Clock out of an overtime shift: the mirror of OT Clock In, for the usual
     * case where the overtime wasn't known about until the end of the day. The
     * punch is an ordinary clock-out; the request is filed alongside it.
     */
    public function otClockOutAction(): Action
    {
        return Action::make('otClockOut')
            ->label('OT Clock Out')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->modalHeading('Clock out with overtime')
            ->modalDescription('Files your overtime request and clocks you out.')
            ->modalSubmitActionLabel('File & clock out')
            ->schema(FileOverTimeRequest::overtimeHoursAndReasonFields())
            ->action(function (array $data): void {
                // Same rule as the normal clock-out: an open shift is required.
                if (! $this->canClockOut()) {
                    return;
                }

                // File against the day the shift started, so a night shift
                // closing after midnight lands on the right DTR row.
                $request = $this->fileOvertime(
                    $data,
                    $this->getClockInLog()?->created_at->toDateString() ?? today()->toDateString(),
                );

                AttendanceLog::create([
                    'user_id' => auth()->id(),
                    'type' => 'clockout',
                    'device' => 'web',
                    'remarks' => "OT — overtime request #{$request->id}",
                ]);

                Notification::make()
                    ->success()
                    ->title('Clocked out with overtime')
                    ->body('Your overtime request has been sent for approval.')
                    ->send();
            });
    }

    /**
     * File the overtime request that accompanies an OT punch.
     *
     * @param  array<string, mixed>  $data
     */
    protected function fileOvertime(array $data, string $requestDate): OverTimeRequest
    {
        return OverTimeRequest::create([
            'user_id' => auth()->id(),
            'request_date' => $requestDate,
            'hours' => $data['hours'],
            'reason' => $data['reason'],
            'status' => AttendanceStatus::FOR_APPROVAL->value,
        ]);
    }

    /**
     * Whether there is an open shift to close.
     */
    public function canClockOut(): bool
    {
        return $this->getClockInLog() !== null && $this->getClockOutLog() === null;
    }

    /**
     * Whether the current shift was started with OT Clock In.
     */
    public function isOvertimeShift(): bool
    {
        return str_contains((string) $this->getClockInLog()?->remarks, 'OT shift');
    }

    public function clockOut(): void
    {
        if (! $this->canClockOut()) {
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
     * The clock-in for the *current* shift to display:
     *  - an open shift (no clock-out after it) always shows, so a night shift
     *    spanning midnight stays visible; otherwise
     *  - today's clock-in, if any.
     *
     * A shift that was completed on a previous day is intentionally not shown,
     * so the widget resets to blank on a new day until the next clock-in.
     */
    public function getClockInLog(): ?AttendanceLog
    {
        $recent = AttendanceLog::query()
            ->where('user_id', auth()->id())
            ->where('type', 'clockin')
            ->where('created_at', '>=', now()->subHours(self::ACTIVE_SHIFT_LOOKBACK_HOURS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($recent === null) {
            return null;
        }

        // Open shift always shows; a completed shift only while it's still today.
        return ($this->clockOutAfter($recent) === null || $recent->created_at->isToday()) ? $recent : null;
    }

    /**
     * The clock-out that closes the current shift. Returns null while the shift
     * is still open.
     */
    public function getClockOutLog(): ?AttendanceLog
    {
        $in = $this->getClockInLog();

        return $in === null ? null : $this->clockOutAfter($in);
    }

    /**
     * The first clock-out that occurs at or after the given clock-in — i.e. the
     * one that closes it. Matched by time so an earlier orphan clock-out can't
     * pair with a later clock-in (e.g. an out-of-order biometric sync that has
     * a higher id but an earlier timestamp). The id only breaks ties between
     * punches written within the same second.
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
     * Whether the employee may start a shift now: not already in an open shift,
     * and hasn't already clocked in today (one scheduled shift per day).
     */
    public function canClockIn(): bool
    {
        // In an open shift the only valid action is clocking out.
        if ($this->getClockInLog() !== null && $this->getClockOutLog() === null) {
            return false;
        }

        return ! $this->hasClockedInToday();
    }

    /**
     * Whether a clock-in already exists for the current calendar day.
     */
    public function hasClockedInToday(): bool
    {
        return AttendanceLog::query()
            ->where('user_id', auth()->id())
            ->where('type', 'clockin')
            ->whereDate('created_at', today())
            ->exists();
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
