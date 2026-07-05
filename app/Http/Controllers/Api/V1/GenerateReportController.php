<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateReportRequest;
use App\Infrastructure\Report\ReportModel;
use App\Infrastructure\Report\ReportStatus;
use App\Jobs\GenerateReportJob;
use Illuminate\Database\QueryException;
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

        try {
            $report = ReportModel::query()->create([
                'id' => (string) Str::uuid7(),
                'requested_by_email' => $request->string('email')->toString(),
                'status' => ReportStatus::PENDING,
                'idempotency_key' => $idempotencyKey,
                'filters_snapshot' => $request->filtersSnapshot(),
            ]);
        } catch (QueryException $e) {
            if ($idempotencyKey === null || ! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }

            // Lost a genuine race: another request carrying the same
            // Idempotency-Key committed its row between our existence
            // check above and this insert. The reports table only has one
            // other unique constraint (its UUIDv7 primary key, which does
            // not realistically collide), so any constraint violation here
            // is that race — fetch and return the winner's row instead of
            // surfacing a raw 500.
            $existing = ReportModel::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

            return response()->json($existing->toStatusPayload(), 200);
        }

        GenerateReportJob::dispatch($report->id);

        return response()->json($report->toStatusPayload(), 202);
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
