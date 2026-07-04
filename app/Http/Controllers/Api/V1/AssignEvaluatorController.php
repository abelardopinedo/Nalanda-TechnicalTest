<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignEvaluatorRequest;
use App\Http\Resources\Api\V1\CandidacyResource;
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
        $updated = $assignEvaluator(new AssignEvaluatorCommand(
            candidacyId: $candidacy,
            evaluatorId: $request->string('evaluator_id')->toString(),
        ));

        return CandidacyResource::make($updated)->response();
    }
}
