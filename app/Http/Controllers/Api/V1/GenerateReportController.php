<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateReportRequest;
use App\Infrastructure\Report\ReportModel;
use App\Infrastructure\Report\ReportStatus;
use App\Jobs\GenerateReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class GenerateReportController extends Controller
{
    public function __invoke(GenerateReportRequest $request): JsonResponse
    {
        $idempotencyKey = $request->idempotencyKey();

        if ($idempotencyKey !== null) {
            $existing = ReportModel::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return response()->json($existing->toStatusPayload(), 200);
            }
        }

        $report = ReportModel::query()->create([
            'id' => (string) Str::uuid7(),
            'requested_by_email' => $request->string('email')->toString(),
            'status' => ReportStatus::PENDING,
            'idempotency_key' => $idempotencyKey,
            'filters_snapshot' => $request->filtersSnapshot(),
        ]);

        GenerateReportJob::dispatch($report->id);

        return response()->json($report->toStatusPayload(), 202);
    }
}
