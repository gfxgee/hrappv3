<?php

namespace App\Services;

use App\Enum\UserStatus;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Notifies the people who need to know when an employee checks in as sick on
 * the moodometer, so the team knows they are offline for the day.
 *
 * The message is deliberately neutral — it states availability, not a
 * diagnosis, and never carries the employee's own wording.
 */
class MoodNotifier
{
    public function __construct(
        private readonly RequestNotifier $requests,
        private readonly TeamsNotifier $teams,
    ) {}

    /**
     * Tell the Teams group chat, plus the employee's manager and HR in-app,
     * that they have checked in as sick.
     */
    public function checkedInSick(User $employee): void
    {
        // Posted to the Teams group chat regardless of who is notified in-app.
        $this->teams->moodCheckedInSick($employee);

        $recipients = $this->recipientsFor($employee);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Sick check-in')
            ->icon(Heroicon::OutlinedFaceFrown)
            ->iconColor('warning')
            ->body("{$employee->displayName()} has checked in as sick today and is offline.")
            ->sendToDatabase($recipients, isEventDispatched: true);
    }

    /**
     * The employee's direct manager plus the usual approvers (department
     * leaders and HR). The employee is never notified about themselves.
     *
     * @return Collection<int, User>
     */
    private function recipientsFor(User $employee): Collection
    {
        $recipients = $this->requests->recipientsFor($employee);

        $manager = $employee->manager;

        if ($manager !== null && $manager->status === UserStatus::ACTIVE->value) {
            $recipients = $recipients->push($manager);
        }

        return $recipients
            ->reject(fn (User $user): bool => $user->is($employee))
            ->unique('id')
            ->values();
    }
}
