<?php

namespace App\Http\Controllers\Mobile;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Widgets\Employee\MyTeamTodayWidget;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\Concerns\FormatsInitials;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use FormatsInitials;

    /**
     * Today's status for everyone in the viewer's department, mirroring
     * {@see MyTeamTodayWidget} with three bulk
     * queries (no per-user querying).
     */
    public function index(Request $request): Response
    {
        $me = $request->user();

        $colleagues = $me->department_id === null
            ? collect()
            : User::query()
                ->active()
                ->where('department_id', $me->department_id)
                ->whereKeyNot($me->id)
                ->orderBy('name')
                ->take(12)
                ->get();

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

        $members = $colleagues->map(function (User $user) use ($clockInDeviceByUser, $leavesByUser): array {
            $leave = $leavesByUser->get($user->id);
            $device = $clockInDeviceByUser->get($user->id);

            [$label, $status] = match (true) {
                $leave?->request_type === LeaveType::SICK => ['Sick leave', 'sick'],
                $leave !== null && $leave->request_type !== LeaveType::WFH => ['On leave', 'leave'],
                $device === 'biometric' => ['In office', 'in'],
                $device === 'web', $device === 'mobile' => ['Work from home', 'wfh'],
                $leave?->request_type === LeaveType::WFH => ['Work from home', 'wfh'],
                default => ['Not clocked in yet', 'out'],
            };

            return [
                'id' => $user->id,
                'name' => $user->display_name ?: $user->name,
                'initials' => $this->initials($user->name),
                'label' => $label,
                'status' => $status,
            ];
        })->values()->all();

        return Inertia::render('mobile/team', [
            'departmentName' => $me->department?->name,
            'today' => now()->format('l, j M'),
            'members' => $members,
        ]);
    }
}
