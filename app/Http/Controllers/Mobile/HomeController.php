<?php

namespace App\Http\Controllers\Mobile;

use App\Enum\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveCreditService;
use App\Services\MobilePunchService;
use App\Services\OnCallService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly MobilePunchService $punch,
        private readonly LeaveCreditService $credits,
        private readonly OnCallService $onCall,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('mobile/home', [
            'greeting' => $this->greeting($user->first_name ?: $user->name),
            'today' => now()->format('l, d M Y'),
            'clock' => $this->punch->snapshot($user),
            'balances' => $this->balances($user),
            'recent' => $this->recentLeaves($user),
            'onCall' => $this->onCallNotice($user),
        ]);
    }

    /**
     * On-call notice for this employee, based on today's effective on-call:
     * the week's owner, or a stand-in covering today. Null when it isn't them.
     *
     * @return array{type: string, range: string, covering_for: string|null}|null
     */
    private function onCallNotice($user): ?array
    {
        $effective = $this->onCall->onCallForDate(today());

        if ($effective === null || ! $effective['user']->is($user)) {
            return null;
        }

        $weekStart = $this->onCall->weekStart(today());

        return [
            'type' => $effective['is_substitute'] ? 'substitute' : 'owner',
            'range' => $weekStart->format('M j').' – '.$weekStart->endOfWeek(CarbonInterface::SUNDAY)->format('M j'),
            'covering_for' => $effective['is_substitute'] ? $effective['primary']?->name : null,
        ];
    }

    /**
     * Remaining leave credit per type, tracked types first.
     *
     * @return list<array{type: string, label: string, icon: string, remaining: float|null, tracked: bool}>
     */
    private function balances($user): array
    {
        return collect(LeaveType::all())
            ->map(function (LeaveType $type) use ($user): array {
                $remaining = $this->credits->remainingDays($user, $type);

                return [
                    'type' => $type->value,
                    'label' => $type->plainLabel(),
                    'icon' => $type->icon(),
                    'remaining' => $remaining,
                    'tracked' => $remaining !== null,
                ];
            })
            ->sortByDesc('tracked')
            ->values()
            ->all();
    }

    /**
     * The employee's five most recent leave requests.
     *
     * @return list<array{id: int, label: string, icon: string, dates: string, status: string, status_label: string}>
     */
    private function recentLeaves($user): array
    {
        return $user->leaveRequests()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'label' => $leave->request_type->plainLabel(),
                'icon' => $leave->request_type->icon(),
                'dates' => $this->formatDateRange($leave->start_date, $leave->end_date),
                'status' => $leave->status->value,
                'status_label' => $leave->status->label(),
            ])
            ->all();
    }

    private function greeting(string $name): string
    {
        $salutation = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return "{$salutation}, {$name}";
    }

    private function formatDateRange($start, $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j, Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('M j').' – '.$end->format('j, Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }
}
