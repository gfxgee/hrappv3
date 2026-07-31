<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnCallService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OnCallController extends Controller
{
    public function __construct(private readonly OnCallService $onCall) {}

    /**
     * The on-call ("late dev") developer for the current week, or the week
     * containing `?date=YYYY-MM-DD` when provided. `name` is null when the
     * roster is empty or everyone is out that whole week.
     */
    public function current(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);
        $weekStart = $this->onCall->weekStart($date);

        $assignment = $this->onCall->assignmentForWeek($date);

        return response()->json([
            'name' => $assignment?->user?->name,
            'display_name' => $assignment?->user?->displayName(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->endOfWeek(CarbonInterface::SUNDAY)->toDateString(),
        ]);
    }

    /**
     * The developer effectively on-call for today (or `?date=`): the week's
     * owner when they're in, otherwise the stand-in covering that day. Use this
     * for "who do I contact right now"; `current` gives the week's owner.
     */
    public function today(Request $request): JsonResponse
    {
        $date = $this->resolveDate($request);
        $effective = $this->onCall->onCallForDate($date);

        $isSubstitute = $effective['is_substitute'] ?? false;

        return response()->json([
            'name' => $effective['user']->name ?? null,
            'display_name' => $effective['user']?->displayName(),
            'is_substitute' => $isSubstitute,
            'covering_for' => $isSubstitute ? $effective['primary']?->name : null,
            'covering_for_display_name' => $isSubstitute ? $effective['primary']?->displayName() : null,
            'date' => $date->toDateString(),
        ]);
    }

    /**
     * The target date: the `date` query param when valid, otherwise today.
     */
    private function resolveDate(Request $request): CarbonImmutable
    {
        if ($request->filled('date')) {
            try {
                return CarbonImmutable::parse((string) $request->query('date'))->startOfDay();
            } catch (Throwable) {
                // Unparseable date → fall back to today.
            }
        }

        return CarbonImmutable::parse(today());
    }
}
