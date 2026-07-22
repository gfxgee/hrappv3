<?php

namespace App\Models;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserData extends Model
{
    public int $working_hours = 8;

    protected $guarded = [];

    /**
     * Never expose the payslip link through array/JSON serialization (e.g. to
     * the Inertia frontend). It is HR/admin-only and surfaced explicitly where
     * authorized — see UserForm. Direct attribute access is unaffected.
     *
     * @var list<string>
     */
    protected $hidden = ['payslip_link'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // public function getVacationLeaveRemainingAttribute(): int
    // {
    //     $vl_hours = DB::table('leave_requests')
    //         ->where('user_id', $this->user_id)
    //         ->where('request_type', 'vacation_leave')
    //         ->whereNot('status', [AttendanceStatus::REJECTED, AttendanceStatus::CANCELLED])
    //         ->selectRaw('SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total_hours')
    //         ->value('total_hours');

    //     $vl_hours = $vl_hours ?? 0;
    //     $vl_hours_int = (int)$vl_hours;
    //     return $this->vacation_leave - ($vl_hours_int / $this->working_hours) ;
    // }

    public function getVacationLeaveRemainingAttribute(): int
    {
        // Determine the database connection type
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            // MySQL-specific time calculation
            $vl_hours = DB::table('leave_requests')
                ->where('user_id', $this->user_id)
                ->where('request_type', LeaveType::VACATION->value)
                ->whereNot('status', [AttendanceStatus::REJECTED, AttendanceStatus::CANCELLED])
                ->selectRaw('SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total_hours')
                ->value('total_hours');
        } else {
            // SQLite-compatible time calculation
            $vl_hours = DB::table('leave_requests')
                ->where('user_id', $this->user_id)
                ->where('request_type', LeaveType::VACATION->value)
                ->whereNot('status', [AttendanceStatus::REJECTED, AttendanceStatus::CANCELLED])
                ->selectRaw('SUM((julianday(end_time) - julianday(start_time)) * 24) as total_hours')
                ->value('total_hours');
        }

        $vl_hours = $vl_hours ?? 0;
        $vl_hours_int = (float) $vl_hours;

        return $this->vacation_leave - ($vl_hours / $this->working_hours);
    }

    public function getSickLeaveRemainingAttribute(): int
    {
        $sl_count = DB::table('leave_requests')
            ->where('user_id', $this->user_id)
            ->where('request_type', LeaveType::SICK->value)
            ->whereNot('status', [AttendanceStatus::REJECTED, AttendanceStatus::CANCELLED])
            ->count();

        return $this->sick_leave - $sl_count;
    }

    public function getEmergencyLeaveRemainingAttribute(): int
    {
        $el_count = DB::table('leave_requests')
            ->where('user_id', $this->user_id)
            ->where('request_type', LeaveType::EMERGENCY->value)
            ->whereNot('status', [AttendanceStatus::REJECTED, AttendanceStatus::CANCELLED])
            ->count();

        return $this->emergency_leave - $el_count;
    }
}
