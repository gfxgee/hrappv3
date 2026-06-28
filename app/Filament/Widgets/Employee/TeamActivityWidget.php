<?php

namespace App\Filament\Widgets\Employee;

use App\Enum\LeaveType;
use App\Enum\Mood;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\MoodCheckIn;
use App\Models\OverTimeRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * A "today only" activity feed across all employees: clock in/out, mood
 * check-ins, and filed leave/overtime, newest first. Nothing from yesterday or
 * earlier is shown — it resets each day.
 */
class TeamActivityWidget extends Widget
{
    protected string $view = 'filament.widgets.employee.team-activity-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2];

    /** Most recent events to show. */
    public const LIMIT = 15;

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Today's team events, newest first.
     *
     * @return Collection<int, array{time: CarbonInterface, icon: string, text: string}>
     */
    public function events(): Collection
    {
        /** @var User $me */
        $me = auth()->user();

        $employees = User::query()
            ->active()
            ->whereKeyNot($me->id)
            ->get(['id', 'name', 'first_name']);

        if ($employees->isEmpty()) {
            return collect();
        }

        $ids = $employees->pluck('id');
        $names = $employees->mapWithKeys(fn (User $u): array => [$u->id => ($u->first_name ?: $u->name)]);

        return collect()
            ->concat($this->clockEvents($ids, $names))
            ->concat($this->moodEvents($ids, $names))
            ->concat($this->leaveEvents($ids, $names))
            ->concat($this->overtimeEvents($ids, $names))
            ->sortByDesc(fn (array $event): CarbonInterface => $event['time'])
            ->take(self::LIMIT)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $names
     * @return Collection<int, array{time: CarbonInterface, icon: string, text: string}>
     */
    protected function clockEvents(Collection $ids, Collection $names): Collection
    {
        return AttendanceLog::query()
            ->whereIn('user_id', $ids)
            ->whereDate('created_at', today())
            ->whereIn('type', ['clockin', 'clockout'])
            ->get(['user_id', 'type', 'created_at'])
            ->map(fn (AttendanceLog $log): array => [
                'time' => $log->created_at,
                'icon' => $log->type === 'clockin' ? '🟢' : '🔴',
                'text' => sprintf('%s clocked %s', $names->get($log->user_id), $log->type === 'clockin' ? 'in' : 'out'),
            ]);
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $names
     * @return Collection<int, array{time: CarbonInterface, icon: string, text: string}>
     */
    protected function moodEvents(Collection $ids, Collection $names): Collection
    {
        return MoodCheckIn::query()
            ->whereIn('user_id', $ids)
            ->forToday()
            ->get(['user_id', 'mood', 'updated_at'])
            ->map(fn (MoodCheckIn $checkIn): array => [
                'time' => $checkIn->updated_at,
                'icon' => $checkIn->mood instanceof Mood ? $checkIn->mood->emoji() : '🙂',
                'text' => sprintf('%s set their mood to %s', $names->get($checkIn->user_id), $checkIn->mood?->label() ?? 'unknown'),
            ]);
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $names
     * @return Collection<int, array{time: CarbonInterface, icon: string, text: string}>
     */
    protected function leaveEvents(Collection $ids, Collection $names): Collection
    {
        return LeaveRequest::query()
            ->whereIn('user_id', $ids)
            ->whereDate('created_at', today())
            ->get(['user_id', 'request_type', 'start_date', 'created_at'])
            ->map(function (LeaveRequest $leave) use ($names): array {
                $type = $leave->request_type instanceof LeaveType ? $leave->request_type->plainLabel() : 'leave';
                $for = $leave->start_date ? ' for '.$leave->start_date->format('M j') : '';

                return [
                    'time' => $leave->created_at,
                    'icon' => '🗓️',
                    'text' => sprintf('%s filed a %s request%s', $names->get($leave->user_id), $type, $for),
                ];
            });
    }

    /**
     * @param  Collection<int, int>  $ids
     * @param  Collection<int, string>  $names
     * @return Collection<int, array{time: CarbonInterface, icon: string, text: string}>
     */
    protected function overtimeEvents(Collection $ids, Collection $names): Collection
    {
        return OverTimeRequest::query()
            ->whereIn('user_id', $ids)
            ->whereDate('created_at', today())
            ->get(['user_id', 'created_at'])
            ->map(fn (OverTimeRequest $ot): array => [
                'time' => $ot->created_at,
                'icon' => '⏱️',
                'text' => sprintf('%s filed an overtime request', $names->get($ot->user_id)),
            ]);
    }
}
