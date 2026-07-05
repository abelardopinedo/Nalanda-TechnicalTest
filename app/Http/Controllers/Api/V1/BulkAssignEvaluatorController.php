<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkAssignEvaluatorRequest;
use App\Infrastructure\Idempotency\IdempotentJsonResponse;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use Candidacy\Application\Command\BulkAssignEvaluatorCommand;
use Candidacy\Application\Exception\EvaluatorNotFoundException;
use Candidacy\Application\UseCase\BulkAssignEvaluator;
use Candidacy\Application\UseCase\SkippedAssignment;
use Illuminate\Http\JsonResponse;

class BulkAssignEvaluatorController extends Controller
{
    public function __invoke(
        string $evaluator,
        BulkAssignEvaluatorRequest $request,
        BulkAssignEvaluator $bulkAssignEvaluator,
    ): JsonResponse {
        if (EvaluatorModel::query()->whereKey($evaluator)->doesntExist()) {
            throw new EvaluatorNotFoundException($evaluator);
        }

        return IdempotentJsonResponse::resolve(
            $request->idempotencyKey(),
            'assign-bulk',
            function () use ($evaluator, $request, $bulkAssignEvaluator): JsonResponse {
                $result = $bulkAssignEvaluator(new BulkAssignEvaluatorCommand(
                    evaluatorId: $evaluator,
                    candidacyIds: $request->candidacyIds(),
                ));

                return response()->json([
                    'assigned' => $result->assigned,
                    'skipped' => array_map(
                        static fn (SkippedAssignment $skipped): array => [
                            'id' => $skipped->candidacyId,
                            'reason' => $skipped->reason,
                        ],
                        $result->skipped,
                    ),
                ]);
            },
        );
    }
}
