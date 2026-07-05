<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignEvaluatorRequest;
use App\Http\Resources\Api\V1\CandidacyResource;
use App\Infrastructure\Cache\CandidacyCacheTags;
use Candidacy\Application\Command\AssignEvaluatorCommand;
use Candidacy\Application\UseCase\AssignEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AssignEvaluatorController extends Controller
{
    /**
     * Idempotency is opt-in (only applied when the client sends an
     * Idempotency-Key header) and stored in Redis — the same cache
     * connection used for the listing/summary read cache — independent of
     * the reports table's own idempotency_key column. TTL is configurable
     * (config('cache.assignment_idempotency_ttl')) so tests can exercise
     * real Redis expiry with a short-lived window instead of 24 hours.
     */
    public function __invoke(
        string $candidacy,
        AssignEvaluatorRequest $request,
        AssignEvaluator $assignEvaluator,
    ): JsonResponse {
        $idempotencyKey = $request->idempotencyKey();
        $cacheKey = $idempotencyKey !== null ? "idempotency:assign:{$idempotencyKey}" : null;

        if ($cacheKey !== null) {
            /** @var \Illuminate\Cache\Repository $store */
            $store = Cache::store(CandidacyCacheTags::STORE);
            $cached = $store->get($cacheKey);

            if ($cached !== null) {
                return response()->json($cached['body'], $cached['status']);
            }
        }

        $updated = $assignEvaluator(new AssignEvaluatorCommand(
            candidacyId: $candidacy,
            evaluatorId: $request->string('evaluator_id')->toString(),
        ));

        $response = CandidacyResource::make($updated)->response();

        if ($cacheKey !== null) {
            /** @var \Illuminate\Cache\Repository $store */
            $store = Cache::store(CandidacyCacheTags::STORE);
            $store->put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true),
            ], (int) config('cache.assignment_idempotency_ttl'));
        }

        return $response;
    }
}
