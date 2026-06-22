<?php

namespace App\Services;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\OverTimeRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Sends in-app (database) notifications to the people who approve a request
 * whenever an employee files a leave or overtime request.
 */
class RequestNotifier
{
    /**
     * Notify the approvers that a leave request was filed.
     */
    public function leaveFiled(LeaveRequest $request): void
    {
        $requester = $request->user;

        if ($requester === null) {
            return;
        }

        $recipients = $this->recipientsFor($requester);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('New leave request')
            ->icon(Heroicon::OutlinedCalendarDateRange)
            ->iconColor('info')
            ->body(sprintf(
                '%s filed a %s request (%s – %s).',
                $requester->name,
                $request->request_type?->plainLabel() ?? 'leave',
                $request->start_date?->format('M j, Y') ?? '',
                $request->end_date?->format('M j, Y') ?? '',
            ))
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->url(LeaveRequestResource::getUrl('edit', ['record' => $request]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    /**
     * Notify the approvers that an overtime request was filed.
     */
    public function overtimeFiled(OverTimeRequest $request): void
    {
        $requester = $request->user;

        if ($requester === null) {
            return;
        }

        $recipients = $this->recipientsFor($requester);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('New overtime request')
            ->icon(Heroicon::OutlinedClock)
            ->iconColor('info')
            ->body(sprintf(
                '%s filed a %s-hour overtime request for %s.',
                $requester->name,
                rtrim(rtrim(number_format((float) $request->hours, 2), '0'), '.'),
                $request->request_date?->format('M j, Y') ?? '',
            ))
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->url(OverTimeRequestResource::getUrl('edit', ['record' => $request]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    /**
     * Notify the approvers that an attendance correction request was filed.
     */
    public function correctionFiled(AttendanceCorrectionRequest $request): void
    {
        $requester = $request->user;

        if ($requester === null) {
            return;
        }

        $recipients = $this->recipientsFor($requester);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('New attendance correction')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconColor('info')
            ->body(sprintf(
                '%s requested a correction (%s) for %s.',
                $requester->name,
                $request->correction_type?->label() ?? 'attendance',
                $request->corrected_at?->format('M j, Y H:i') ?? '',
            ))
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->url(AttendanceCorrectionRequestResource::getUrl('index'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    /**
     * Resolve who should be notified about a request from the given employee.
     *
     * Active team leaders of the employee's department plus active HR staff
     * are notified. When neither exists, the request falls back to the
     * super-admins so it never goes unseen. The requester is never notified
     * about their own filing.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(User $requester): Collection
    {
        $teamLeaders = $requester->department
            ? $requester->department->leaders()->active()->get()
            : collect();

        $recipients = $teamLeaders
            ->merge($this->usersWithRoles(['hr']))
            ->reject(fn (User $user): bool => $user->is($requester));

        if ($recipients->isEmpty()) {
            $recipients = $this->usersWithRoles(['superadmin', 'super_admin'])
                ->reject(fn (User $user): bool => $user->is($requester));
        }

        return $recipients->unique('id')->values();
    }

    /**
     * Active users holding any of the given roles.
     *
     * Uses a relationship query rather than Spatie's `role()` scope so it
     * returns empty (instead of throwing) when a role has not been seeded.
     *
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    protected function usersWithRoles(array $roles): Collection
    {
        return User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->get();
    }
}
