<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CandidacyEvaluatorListingRequest;
use App\Http\Resources\Api\V1\CandidacyEvaluatorListingResource;
use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidacyEvaluatorListingController extends Controller
{
    public function __invoke(
        CandidacyEvaluatorListingRequest $request,
        CandidacyEvaluatorListingQuery $query,
    ): AnonymousResourceCollection {
        $paginator = $query->paginate(
            sort: $request->sort(),
            direction: $request->direction(),
            filters: $request->filters(),
            perPage: $request->perPage(),
            page: $request->page(),
        );

        return CandidacyEvaluatorListingResource::collection($paginator);
    }
}
