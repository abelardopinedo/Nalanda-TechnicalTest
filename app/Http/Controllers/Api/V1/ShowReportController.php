<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Report\ReportModel;
use Illuminate\Http\JsonResponse;

class ShowReportController extends Controller
{
    public function __invoke(string $report): JsonResponse
    {
        $model = ReportModel::query()->find($report);

        abort_if($model === null, 404, 'Report not found.');

        return response()->json($model->toStatusPayload());
    }
}
