<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BiometricPunchRequest;
use App\Services\AttendancePunchService;
use Illuminate\Http\JsonResponse;

class BiometricPunchController extends Controller
{
    public function __construct(private readonly AttendancePunchService $punches) {}

    /**
     * Ingest a single biometric punch from the SharePoint/Power Automate flow.
     *
     * Always responds 200 for handled outcomes (created / duplicate / unmatched)
     * so the flow does not retry punches that can't be applied (e.g. an unknown
     * employee). Bad secret → 401 (middleware); malformed body → 422.
     */
    public function __invoke(BiometricPunchRequest $request): JsonResponse
    {
        $result = $this->punches->record($request->toPunch());

        return response()->json($result);
    }
}
