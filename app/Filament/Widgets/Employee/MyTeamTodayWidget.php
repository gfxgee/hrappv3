<?php

namespace App\Filament\Widgets\Employee;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Today's status for everyone in the viewer's department: in office
 * (clocked in), remote (approved WFH), sick / on leave, or no activity yet.
 */
class MyTeamTodayWidget extends Widget
{
    protected string $view = 'filament.widgets.employee.my-team-today-widget';

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2];

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function hasDepartment(): bool
    {
        return auth()->user()?->department_id !== null;
    }

    /**
     * Department colleagues with a status label and color, built from three
     * bulk queries (no per-user querying).
     *
     * @return Collection<int, array{user: User, status: string, color: string}>
     */
    public function members(): Collection
    {
        /** @var User $me */
        $me = auth()->user();

        if ($me->department_id === null) {
            return collect();
        }

        $colleagues = User::query()
            ->active()
            ->where('department_id', $me->department_id)
            ->whereKeyNot($me->id)
            ->orderBy('name')
            ->take(12)
            ->get();

        // The device of each colleague's first clock-in today drives their
        // status: a biometric scan means they're in the office, a web clock-in
        // means they're working from home.
        $clockInDeviceByUser = AttendanceLog::query()
            ->whereIn('user_id', $colleagues->pluck('id'))
            ->whereDate('created_at', today())
            ->where('type', 'clockin')
            ->orderBy('created_at')
            ->get(['user_id', 'device'])
            ->groupBy('user_id')
            ->map(fn ($logs): ?string => $logs->first()->device);

        $leavesByUser = LeaveRequest::query()
            ->whereIn('user_id', $colleagues->pluck('id'))
            ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->get()
            ->keyBy('user_id');

        return $colleagues->map(function (User $user) use ($clockInDeviceByUser, $leavesByUser): array {
            $leave = $leavesByUser->get($user->id);
            $device = $clockInDeviceByUser->get($user->id);

            [$status, $color] = match (true) {
                $leave?->request_type === LeaveType::SICK => ['Sick', 'danger'],
                $leave !== null && $leave->request_type !== LeaveType::WFH => ['On leave', 'warning'],
                $device === 'biometric' => ['In office', 'success'],
                $device === 'web' => ['Work from home', 'info'],
                $leave?->request_type === LeaveType::WFH => ['Work from home', 'info'],
                default => ['—', 'gray'],
            };

            return ['user' => $user, 'status' => $status, 'color' => $color];
        });
    }
}
