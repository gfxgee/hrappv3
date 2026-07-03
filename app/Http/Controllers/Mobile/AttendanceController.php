<?php

namespace App\Http\Controllers\Mobile;

use App\Enum\AttendanceCorrectionType;
use App\Enum\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Services\DtrService;
use App\Services\MobilePunchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly DtrService $dtr,
        private readonly MobilePunchService $punch,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $from = now()->startOfWeek();
        $to = now()->endOfWeek();

        $dtr = $this->dtr->build($user, $from, $to);

        return Inertia::render('mobile/attendance', [
            'weekLabel' => $from->format('j M').' – '.$to->format('j M, Y'),
            'today' => $this->punch->snapshot($user),
            'rows' => collect($dtr['rows'])
                ->map(fn (array $row): array => [
                    'day' => $row['day'],
                    'date' => $row['date']->format('j M'),
                    'time_in' => $row['time_in'],
                    'time_out' => $row['time_out'],
                    'hours' => $this->hoursToHuman($row['hours']),
                    'status' => $row['status'],
                ])
                ->all(),
            'totals' => [
                'hours' => $this->hoursToHuman($dtr['totals']['hours']),
                'present' => $dtr['totals']['present'],
                'leave' => $dtr['totals']['leave'],
                'absent' => $dtr['totals']['absent'],
            ],
            'correctionTypes' => AttendanceCorrectionType::toArray(),
        ]);
    }

    public function storeCorrection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'correction_type' => ['required', Rule::enum(AttendanceCorrectionType::class)],
            'target_log_type' => [
                Rule::requiredIf(fn (): bool => $request->input('correction_type') === AttendanceCorrectionType::WRONG_TIME->value),
                'nullable',
                'in:clockin,clockout',
            ],
            'corrected_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        AttendanceCorrectionRequest::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => AttendanceStatus::FOR_APPROVAL->value,
        ]);

        return back()->with('success', 'Correction submitted. HR will review your record.');
    }

    /**
     * Format decimal hours (e.g. 9.06) as a human "9h 04m" string.
     */
    private function hoursToHuman(float $hours): string
    {
        $minutes = (int) round($hours * 60);

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
