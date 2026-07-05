<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignEvaluatorRequest;
use App\Http\Resources\Api\V1\CandidacyResource;
use App\Infrastructure\Idempotency\IdempotentJsonResponse;
use Candidacy\Application\Command\AssignEvaluatorCommand;
use Candidacy\Application\UseCase\AssignEvaluator;
use Illuminate\Http\JsonResponse;

class AssignEvaluatorController extends Controller
{
    public function __invoke(
        string $candidacy,
        AssignEvaluatorRequest $request,
        AssignEvaluator $assignEvaluator,
    ): JsonResponse {
        return IdempotentJsonResponse::resolve(
            $request->idempotencyKey(),
            'assign',
            function () use ($candidacy, $request, $assignEvaluator): JsonResponse {
                $updated = $assignEvaluator(new AssignEvaluatorCommand(
                    candidacyId: $candidacy,
                    evaluatorId: $request->string('evaluator_id')->toString(),
                ));

                return CandidacyResource::make($updated)->response();
            },
        );
    }
}
