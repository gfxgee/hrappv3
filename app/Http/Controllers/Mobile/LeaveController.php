<?php

namespace App\Http\Controllers\Mobile;

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveCreditService $credits) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'request_type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $type = LeaveType::from($validated['request_type']);

        $this->assertWithinBalance($user, $type, $validated);

        LeaveRequest::create([
            ...$validated,
            'user_id' => $user->id,
            'status' => AttendanceStatus::FOR_APPROVAL->value,
        ]);

        return back()->with('success', 'Leave request submitted. Your approver has been notified.');
    }

    /**
     * Block a request that exceeds the remaining credit for a tracked type,
     * mirroring the FileLeaveRequest page's balance guard.
     *
     * @param  array{start_date: string, end_date: string, start_time?: ?string, end_time?: ?string}  $data
     */
    private function assertWithinBalance($user, LeaveType $type, array $data): void
    {
        $remaining = $this->credits->remainingDays($user, $type);

        if ($remaining === null) {
            return;
        }

        $draft = new LeaveRequest($data);
        $requested = $draft->durationInDays(
            $this->credits->workingHoursFor($user->userData),
            $this->credits->holidayDates(),
        );

        if ($requested > $remaining) {
            throw ValidationException::withMessages([
                'end_date' => "You only have {$remaining} day(s) of {$type->plainLabel()} left, but requested {$requested}.",
            ]);
        }
    }
}
