<?php
$u = App\Models\User::find(2);
auth()->login($u);
App\Models\LeaveRequest::create([
    'user_id' => 2,
    'request_type' => App\Enum\LeaveType::VACATION->value,
    'start_date' => '2026-07-01',
    'end_date' => '2026-07-02',
    'reason' => 'debug',
    'status' => App\Enum\AttendanceStatus::FOR_APPROVAL->value,
]);
$cr = collect((new App\Filament\Pages\FileLeaveRequest())->getLeaveCredits())->keyBy('label');
echo json_encode([
    'vacation' => $cr['Vacation Leave'] ?? null,
    'sick' => $cr['Sick Leave'] ?? null,
], JSON_PRETTY_PRINT)."\n";
App\Models\LeaveRequest::where('reason', 'debug')->delete();
