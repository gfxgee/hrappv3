<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts request events to a Microsoft Power Automate flow, which relays them
 * to a Teams channel. No-op when no endpoint is configured, and never lets a
 * delivery failure interrupt the employee's action.
 */
class TeamsNotifier
{
    public function leaveFiled(LeaveRequest $request): void
    {
        $this->sendLeave($request, 'leave.filed', 'filed a');
    }

    public function leaveEdited(LeaveRequest $request): void
    {
        $this->sendLeave($request, 'leave.edited', 'updated their');
    }

    public function leaveCancelled(LeaveRequest $request): void
    {
        $this->sendLeave($request, 'leave.cancelled', 'cancelled their');
    }

    public function overtimeFiled(OverTimeRequest $request): void
    {
        $this->sendOvertime($request, 'overtime.filed', 'filed a');
    }

    public function overtimeEdited(OverTimeRequest $request): void
    {
        $this->sendOvertime($request, 'overtime.edited', 'updated their');
    }

    public function overtimeCancelled(OverTimeRequest $request): void
    {
        $this->sendOvertime($request, 'overtime.cancelled', 'cancelled their');
    }

    /**
     * Announce the on-call ("late dev") developer for a week.
     */
    public function onCallAssigned(User $user, CarbonInterface $weekStart): void
    {
        $weekEnd = $weekStart->endOfWeek(CarbonInterface::SUNDAY);
        $range = $weekStart->format('M j').' – '.$weekEnd->format('M j');

        $this->send([
            'event' => 'on_call.assigned',
            'category' => 'On-Call',
            'icon' => '📞',
            'employee' => $user->name,
            'email' => $user->email,
            'photo' => $user->getFilamentAvatarUrl(),
            'department' => $user->department?->name,
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekEnd->toDateString(),
            'text' => sprintf('📞 %s is on-call this week (%s).', $user->name, $range),
        ]);
    }

    /**
     * Announce that a stand-in is covering on-call for a day (the owner is on
     * leave).
     */
    public function onCallStandIn(User $standIn, ?User $primary, CarbonInterface $date): void
    {
        $this->send([
            'event' => 'on_call.standin',
            'category' => 'On-Call',
            'icon' => '📞',
            'employee' => $standIn->name,
            'email' => $standIn->email,
            'photo' => $standIn->getFilamentAvatarUrl(),
            'department' => $standIn->department?->name,
            'covering_for' => $primary?->name,
            'request_date' => $date->toDateString(),
            'text' => sprintf(
                '📞 %s is covering on-call today%s.',
                $standIn->name,
                $primary !== null ? ' for '.$primary->name.' (on leave)' : '',
            ),
        ]);
    }

    public function correctionFiled(AttendanceCorrectionRequest $request): void
    {
        $user = $request->user;
        $type = $request->correction_type?->label() ?? 'Attendance';

        $this->send([
            'event' => 'attendance_correction.filed',
            'category' => 'Attendance Correction',
            'icon' => '✏️',
            'employee' => $user?->name,
            'email' => $user?->email,
            'photo' => $user?->getFilamentAvatarUrl(),
            'department' => $user?->department?->name,
            'request_date' => $request->corrected_at?->toDateString(),
            'approver' => $this->approverEmail($user),
            'reason' => $request->reason,
            'status' => $request->status?->label(),
            'filed_at' => $request->created_at?->toIso8601String(),
            'text' => sprintf(
                '✏️ %s filed an attendance correction (%s) for %s.',
                $user?->name ?? 'An employee',
                $type,
                $request->corrected_at?->format('M j, Y H:i') ?? '',
            ),
        ]);
    }

    protected function sendLeave(LeaveRequest $request, string $event, string $verb): void
    {
        $user = $request->user;
        $type = $request->request_type?->plainLabel() ?? 'Leave';

        $range = $request->start_date?->format('M j, Y') ?? '';

        if ($request->end_date && $request->start_date && ! $request->start_date->isSameDay($request->end_date)) {
            $range .= ' – '.$request->end_date->format('M j, Y');
        }

        $this->send([
            'event' => $event,
            'category' => 'Leave',
            'icon' => $request->request_type?->icon() ?? '🌴',
            'employee' => $user?->name,
            'email' => $user?->email,
            'photo' => $user?->getFilamentAvatarUrl(),
            'department' => $user?->department?->name,
            'leave_type' => $type,
            'start_date' => $request->start_date?->toDateString(),
            'end_date' => $request->end_date?->toDateString(),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'request_date' => null,
            'hours' => null,
            'approver' => $this->approverEmail($user),
            'reason' => $request->reason,
            'status' => $request->status?->label(),
            'filed_at' => $request->created_at?->toIso8601String(),
            'text' => sprintf('%s %s %s %s request (%s).', $this->prefix($event, '🌴'), $user?->name ?? 'An employee', $verb, $type, $range),
        ]);
    }

    protected function sendOvertime(OverTimeRequest $request, string $event, string $verb): void
    {
        $user = $request->user;
        $hours = rtrim(rtrim(number_format((float) $request->hours, 2), '0'), '.');

        $this->send([
            'event' => $event,
            'category' => 'Overtime',
            'icon' => '⏱️',
            'employee' => $user?->name,
            'email' => $user?->email,
            'photo' => $user?->getFilamentAvatarUrl(),
            'department' => $user?->department?->name,
            'leave_type' => null,
            'start_date' => null,
            'end_date' => null,
            'start_time' => null,
            'end_time' => null,
            'request_date' => $request->request_date?->toDateString(),
            'hours' => (float) $request->hours,
            'approver' => $this->approverEmail($user),
            'reason' => $request->reason,
            'status' => $request->status?->label(),
            'filed_at' => $request->created_at?->toIso8601String(),
            'text' => sprintf(
                '%s %s %s %s-hour overtime request for %s.',
                $this->prefix($event, '⏰'),
                $user?->name ?? 'An employee',
                $verb,
                $hours,
                $request->request_date?->format('M j, Y') ?? '',
            ),
        ]);
    }

    /**
     * Leading emoji for the summary text based on the event.
     */
    protected function prefix(string $event, string $filedEmoji): string
    {
        return match (true) {
            str_ends_with($event, '.cancelled') => '❌',
            str_ends_with($event, '.edited') => '✏️',
            default => $filedEmoji,
        };
    }

    /**
     * The email to @mention for approval: the team leader of the employee's
     * department, or the configured default when no leader is set.
     */
    protected function approverEmail(?User $user): string
    {
        $leaderEmail = $user?->department?->leaders()->orderBy('name')->value('users.email');

        return $leaderEmail ?: (string) config('services.teams.default_approver', 'vevien@digitalfeet.com');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(array $payload): void
    {
        $url = config('services.teams.flow_url');

        if (blank($url)) {
            return;
        }

        // Drop null fields so the Power Automate trigger's schema validation
        // never sees a typed property (e.g. "hours": number) as null → 400.
        $payload = array_filter($payload, static fn ($value): bool => $value !== null);

        try {
            $response = Http::acceptJson()->timeout(8)->post($url, $payload);

            if ($response->failed()) {
                Log::warning('Teams flow notification failed.', [
                    'status' => $response->status(),
                    'event' => $payload['event'] ?? null,
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
