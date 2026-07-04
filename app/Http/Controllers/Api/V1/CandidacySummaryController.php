<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CandidacySummaryResource;
use App\Infrastructure\Query\CandidacySummaryQuery;
use Candidacy\Application\Exception\CandidacyNotFoundException;
use Illuminate\Http\JsonResponse;

class CandidacySummaryController extends Controller
{
    public function __invoke(string $candidacy, CandidacySummaryQuery $query): JsonResponse
    {
        $summary = $query->forCandidacy($candidacy);

        if ($summary === null) {
            throw new CandidacyNotFoundException($candidacy);
        }

        return CandidacySummaryResource::make($summary)->response();
    }
}
