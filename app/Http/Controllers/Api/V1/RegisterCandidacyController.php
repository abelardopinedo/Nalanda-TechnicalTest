<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterCandidacyRequest;
use App\Http\Resources\Api\V1\CandidacyResource;
use Candidacy\Application\Command\RegisterCandidacyCommand;
use Candidacy\Application\UseCase\RegisterCandidacy;
use Illuminate\Http\JsonResponse;

class RegisterCandidacyController extends Controller
{
    public function __invoke(RegisterCandidacyRequest $request, RegisterCandidacy $registerCandidacy): JsonResponse
    {
        $candidacy = $registerCandidacy(new RegisterCandidacyCommand(
            fullName: $request->string('full_name')->toString(),
            email: $request->string('email')->toString(),
            yearsOfExperience: $request->integer('years_of_experience'),
            cvText: $request->string('cv_text')->toString(),
        ));

        return CandidacyResource::make($candidacy)
            ->response()
            ->setStatusCode(201);
    }
}
