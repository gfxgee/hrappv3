<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LeaveSummaryController extends Controller
{
    public function __construct(private readonly LeaveSummaryService $summary) {}

    /**
     * Per-employee leave and overtime totals for a date range — one payload for
     * payslip automation. Covers every active employee (inactive are excluded),
     * counting only approved/verified records.
     *
     * `?start=YYYY-MM-DD&end=YYYY-MM-DD`; both default to the current month, and
     * a reversed range is swapped rather than returning nothing.
     */
    public function __invoke(Request $request): JsonResponse
    {
        [$start, $end] = $this->resolveRange($request);

        return response()->json($this->summary->summarize($start, $end));
    }

    /**
     * The requested range, defaulting to the current calendar month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request): array
    {
        $today = CarbonImmutable::parse(today());

        $start = $this->parseDate($request->query('start')) ?? $today->startOfMonth();
        $end = $this->parseDate($request->query('end')) ?? $today->endOfMonth()->startOfDay();

        // Tolerate a reversed range instead of returning an empty report.
        return $start->greaterThan($end) ? [$end, $start] : [$start, $end];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
