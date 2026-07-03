<?php

namespace App\Http\Controllers\Mobile;

use App\Enum\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\OverTimeRequest;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OvertimeController extends Controller
{
    public function store(Request $request, GeneralSettings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'request_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:'.$settings->maxOvertimeHours],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        OverTimeRequest::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => AttendanceStatus::FOR_APPROVAL->value,
        ]);

        return back()->with('success', 'Overtime request submitted. Your approver has been notified.');
    }
}
