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

        $clockedInIds = AttendanceLog::query()
            ->whereIn('user_id', $colleagues->pluck('id'))
            ->whereDate('created_at', today())
            ->where('type', 'clockin')
            ->pluck('user_id')
            ->flip();

        $leavesByUser = LeaveRequest::query()
            ->whereIn('user_id', $colleagues->pluck('id'))
            ->whereIn('status', [AttendanceStatus::APPROVED->value, AttendanceStatus::APPROVED_AND_VERIFIED->value])
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->get()
            ->keyBy('user_id');

        return $colleagues->map(function (User $user) use ($clockedInIds, $leavesByUser): array {
            $leave = $leavesByUser->get($user->id);

            [$status, $color] = match (true) {
                $leave?->request_type === LeaveType::WFH => ['Remote', 'info'],
                $leave?->request_type === LeaveType::SICK => ['Sick', 'danger'],
                $leave !== null => ['On leave', 'warning'],
                $clockedInIds->has($user->id) => ['In office', 'success'],
                default => ['—', 'gray'],
            };

            return ['user' => $user, 'status' => $status, 'color' => $color];
        });
    }
}
