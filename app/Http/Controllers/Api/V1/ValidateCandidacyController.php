<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ValidationReportResource;
use Candidacy\Application\Command\ValidateCandidacyCommand;
use Candidacy\Application\UseCase\ValidateCandidacy;
use Illuminate\Http\JsonResponse;

class ValidateCandidacyController extends Controller
{
    public function __invoke(string $candidacy, ValidateCandidacy $validateCandidacy): JsonResponse
    {
        $outcome = $validateCandidacy(new ValidateCandidacyCommand($candidacy));

        return ValidationReportResource::make($outcome->report)->response();
    }
}
