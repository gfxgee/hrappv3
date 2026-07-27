<?php

namespace App\Services;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\LeaveRequest;
use App\Models\OnCallAssignment;
use App\Models\OnCallMember;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\TimeOptions;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Weekly on-call ("late dev") rotation.
 *
 * The pick for a week is deterministic: among roster members who are available
 * that whole week (not on non-WFH leave every working day), choose the one who
 * was on-call least recently — members never assigned come first — breaking
 * ties by roster position. This reproduces a simple 1-2-3-4 rotation while
 * automatically deferring anyone who is out for their whole turn.
 */
class OnCallService
{
    /**
     * The Monday (start of week) for the given date.
     */
    public function weekStart(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * The persisted assignment for the week containing $date, computing and
     * (optionally) persisting one when none exists yet.
     */
    public function assignmentForWeek(CarbonInterface $date, bool $persist = false): ?OnCallAssignment
    {
        $weekStart = $this->weekStart($date);

        $existing = OnCallAssignment::query()
            ->whereDate('week_start', $weekStart)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $user = $this->pickForWeek($weekStart);

        if (! $persist) {
            return $user === null ? null : new OnCallAssignment([
                'week_start' => $weekStart,
                'user_id' => $user->id,
                'is_override' => false,
            ]);
        }

        return OnCallAssignment::create([
            'week_start' => $weekStart,
            'user_id' => $user?->id,
            'is_override' => false,
        ]);
    }

    /**
     * The user who should be on-call for the given week, or null when the
     * roster is empty or everyone is out the whole week.
     */
    public function pickForWeek(CarbonInterface $weekStart): ?User
    {
        $weekStart = $this->weekStart($weekStart);
        $roster = $this->roster();

        if ($roster->isEmpty()) {
            return null;
        }

        return $this->selectMember($roster, $weekStart, $this->lastOnCallBefore($weekStart))?->user;
    }

    /**
     * Project the rotation forward from the current week, returning the on-call
     * user id for each of the next $weeks weeks. Persisted assignments (and
     * manual overrides) are honoured; the rest are computed with the same
     * availability + least-recently rules, so the preview matches reality.
     *
     * @return list<array{week_start: CarbonImmutable, user_id: int|null}>
     */
    public function projectSchedule(int $weeks): array
    {
        $roster = $this->roster();

        if ($roster->isEmpty() || $weeks < 1) {
            return [];
        }

        $current = $this->weekStart(today());
        $lastOnCall = $this->lastOnCallBefore($current);
        $schedule = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $current->addWeeks($i);

            $persisted = OnCallAssignment::query()->whereDate('week_start', $weekStart)->first();

            $userId = $persisted !== null
                ? $persisted->user_id
                : $this->selectMember($roster, $weekStart, $lastOnCall)?->user_id;

            if ($userId !== null) {
                $lastOnCall[$userId] = $weekStart->toDateString();
            }

            $schedule[] = ['week_start' => $weekStart, 'user_id' => $userId];
        }

        return $schedule;
    }

    /**
     * The next week each roster member is scheduled to be on-call, keyed by
     * user id. Looks ahead far enough that everyone gets a turn even with
     * deferrals; anyone still unscheduled in the window is omitted.
     *
     * @return array<int, CarbonImmutable>
     */
    public function nextOnCallWeekByUser(): array
    {
        $roster = $this->roster();
        $weeks = max(8, $roster->count() * 3);

        $next = [];

        foreach ($this->projectSchedule($weeks) as $week) {
            if ($week['user_id'] !== null && ! isset($next[$week['user_id']])) {
                $next[$week['user_id']] = $week['week_start'];
            }
        }

        return $next;
    }

    /**
     * The ordered roster (members with an existing user), by position.
     *
     * @return Collection<int, OnCallMember>
     */
    private function roster(): Collection
    {
        return OnCallMember::query()
            ->with('user')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(fn (OnCallMember $member): bool => $member->user !== null)
            ->values();
    }

    /**
     * Last week ("Y-m-d") each user was on-call before $weekStart, keyed by id.
     *
     * @return array<int, string>
     */
    private function lastOnCallBefore(CarbonImmutable $weekStart): array
    {
        return OnCallAssignment::query()
            ->whereNotNull('user_id')
            ->whereDate('week_start', '<', $weekStart)
            ->orderBy('week_start')
            ->get(['user_id', 'week_start'])
            ->keyBy('user_id')
            ->map(fn (OnCallAssignment $assignment): string => $assignment->week_start->toDateString())
            ->all();
    }

    /**
     * Choose the on-call member for a week from the roster: available all week,
     * least recently on-call (never-assigned first), tie-broken by position.
     *
     * @param  Collection<int, OnCallMember>  $roster
     * @param  array<int, string>  $lastOnCall  user id => last "Y-m-d" week
     */
    private function selectMember(Collection $roster, CarbonImmutable $weekStart, array $lastOnCall): ?OnCallMember
    {
        $available = $roster->filter(
            fn (OnCallMember $member): bool => $this->isAvailableWholeWeek($member->user, $weekStart),
        );

        if ($available->isEmpty()) {
            return null;
        }

        // Sort key: least-recently on-call first (never-assigned = 0, so they
        // lead), then by roster position. Dates collapse to a YYYYMMDD number so
        // an older week always sorts before a newer one.
        return $available
            ->sortBy(function (OnCallMember $member) use ($lastOnCall): int {
                $last = $lastOnCall[$member->user_id] ?? null;
                $recency = $last === null ? 0 : (int) str_replace('-', '', $last);

                return $recency * 1000 + $member->position;
            })
            ->first();
    }

    /**
     * True when the user is available on at least one working day of the week,
     * i.e. NOT on full-day leave every working day. (Partial-day leaves don't
     * count against them — see isAvailableOnDate.)
     */
    public function isAvailableWholeWeek(User $user, CarbonInterface $weekStart): bool
    {
        $weekStart = $this->weekStart($weekStart);
        $workingDates = $this->workingDatesInWeek($weekStart);

        // No working days configured in this week → treat as available.
        if ($workingDates === []) {
            return true;
        }

        $leaves = $this->fullDayLeavesBetween($user, $weekStart, $weekStart->endOfWeek(CarbonInterface::SUNDAY));
        $workingHours = $this->workingHoursFor($user);

        foreach ($workingDates as $date) {
            if (! $this->hasFullDayAbsence($leaves, $date, $workingHours)) {
                return true; // free at least one working day → around that week
            }
        }

        return false;
    }

    /**
     * True when the user is available for on-call on the given day. Only a
     * FULL-day non-WFH leave makes them unavailable — a partial-day leave (e.g.
     * a 10am–1pm errand) still leaves them on-call for the day.
     */
    public function isAvailableOnDate(User $user, CarbonInterface $date): bool
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $leaves = $this->fullDayLeavesBetween($user, $date, $date);

        return ! $this->hasFullDayAbsence($leaves, $date, $this->workingHoursFor($user));
    }

    /**
     * Active non-WFH leaves for the user overlapping the given date range.
     *
     * @return Collection<int, LeaveRequest>
     */
    private function fullDayLeavesBetween(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('request_type', '!=', LeaveType::WFH->value)
            ->whereNotIn('status', [
                AttendanceStatus::REJECTED->value,
                AttendanceStatus::CANCELLED->value,
            ])
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get(['start_date', 'end_date', 'start_time', 'end_time']);
    }

    /**
     * Whether any of the leaves keeps the user out for the whole working day on
     * $date.
     *
     * @param  Collection<int, LeaveRequest>  $leaves
     */
    private function hasFullDayAbsence(Collection $leaves, CarbonImmutable $date, float $workingHours): bool
    {
        return $leaves->contains(function (LeaveRequest $leave) use ($date, $workingHours): bool {
            if ($leave->start_date->greaterThan($date) || $leave->end_date->lessThan($date)) {
                return false;
            }

            // A multi-day leave is a full day off on any day it spans.
            if (! $leave->start_date->isSameDay($leave->end_date)) {
                return true;
            }

            // Single-day leave: full day only when it has no times, or its
            // hours cover (or exceed) a normal working day.
            $hours = TimeOptions::durationHours($leave->start_time, $leave->end_time);

            return $hours === null || $hours >= $workingHours;
        });
    }

    /**
     * The length of a standard working day for the user, in hours.
     */
    private function workingHoursFor(User $user): float
    {
        return app(LeaveCreditService::class)->workingHoursFor($user->userData);
    }

    /**
     * The developer effectively on-call for a specific day: the week's owner
     * when they're in, otherwise the next available roster member (in rotation
     * order after the owner) standing in for the day. Null when the roster is
     * empty or nobody is available.
     *
     * @return array{user: User, primary: ?User, is_substitute: bool}|null
     */
    public function onCallForDate(CarbonInterface $date): ?array
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $roster = $this->roster();

        if ($roster->isEmpty()) {
            return null;
        }

        $owner = $this->assignmentForWeek($date)?->user;

        // The week's owner covers the day whenever they're actually in.
        if ($owner !== null && $this->isAvailableOnDate($owner, $date)) {
            return ['user' => $owner, 'primary' => $owner, 'is_substitute' => false];
        }

        // Otherwise the next available member in rotation order stands in.
        foreach ($this->rotationOrderFrom($roster, $owner) as $member) {
            if ($this->isAvailableOnDate($member->user, $date)) {
                return [
                    'user' => $member->user,
                    'primary' => $owner,
                    'is_substitute' => $owner !== null,
                ];
            }
        }

        return null;
    }

    /**
     * Whether the given day is a working day per the app's working-days setting.
     */
    public function isWorkingDay(CarbonInterface $date): bool
    {
        /** @var list<int> $workingDays */
        $workingDays = app(GeneralSettings::class)->workingDays;

        return in_array(CarbonImmutable::parse($date)->dayOfWeekIso, $workingDays, true);
    }

    /**
     * The roster reordered to start just after the owner (so substitutes are
     * chosen "next in rotation"), cycling back around with the owner last.
     *
     * @param  Collection<int, OnCallMember>  $roster
     * @return Collection<int, OnCallMember>
     */
    private function rotationOrderFrom(Collection $roster, ?User $owner): Collection
    {
        if ($owner === null) {
            return $roster->values();
        }

        $ownerIndex = $roster->search(fn (OnCallMember $member): bool => $member->user->is($owner));

        if ($ownerIndex === false) {
            return $roster->values();
        }

        return $roster->slice($ownerIndex + 1)
            ->concat($roster->slice(0, $ownerIndex + 1))
            ->values();
    }

    /**
     * The working-day dates within the week, per the app's working-days setting.
     *
     * @return list<CarbonImmutable>
     */
    private function workingDatesInWeek(CarbonImmutable $weekStart): array
    {
        /** @var list<int> $workingDays */
        $workingDays = app(GeneralSettings::class)->workingDays;

        $dates = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);

            if (in_array($date->dayOfWeekIso, $workingDays, true)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
