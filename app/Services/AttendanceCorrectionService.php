<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;

/**
 * Applies an approved attendance correction to the employee's attendance log:
 * adjusts the matching punch if one exists that day, otherwise creates it.
 */
class AttendanceCorrectionService
{
    public function apply(AttendanceCorrectionRequest $request): void
    {
        $type = $request->resolveTargetLogType();

        // "Other" corrections carry no specific punch to apply — HR handles
        // those by hand. Nothing to do without a target time either.
        if ($type === null || $request->corrected_at === null) {
            return;
        }

        // Find the punch of this type already logged on the corrected date. If
        // the employee moved the punch to a different day, none will match and
        // we create a fresh one (leaving the original for HR to remove).
        $existing = AttendanceLog::query()
            ->where('user_id', $request->user_id)
            ->where('type', $type)
            ->whereDate('created_at', $request->corrected_at->toDateString())
            ->orderBy('created_at')
            ->first();

        if ($existing !== null) {
            $existing->update([
                'created_at' => $request->corrected_at,
                'remarks' => "Adjusted via correction #{$request->id}",
            ]);

            return;
        }

        AttendanceLog::create([
            'user_id' => $request->user_id,
            'type' => $type,
            'device' => 'web',
            'created_at' => $request->corrected_at,
            'remarks' => "Added via correction #{$request->id}",
        ]);
    }
}
