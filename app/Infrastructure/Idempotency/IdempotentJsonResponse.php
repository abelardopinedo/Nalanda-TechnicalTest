<?php

namespace App\Infrastructure\Idempotency;

use App\Infrastructure\Cache\CandidacyCacheTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed idempotency-key replay shared by every evaluator-assignment
 * endpoint (single and bulk): on a cache hit, returns the previously stored
 * response verbatim instead of invoking $operation again; on a miss, runs
 * $operation and stores its response under the key. Idempotency is opt-in
 * — a null key always just runs $operation. Independent of the reports
 * table's own idempotency_key column.
 */
final class IdempotentJsonResponse
{
    public static function resolve(?string $idempotencyKey, string $namespace, callable $operation): JsonResponse
    {
        if ($idempotencyKey === null) {
            return $operation();
        }

        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);
        $cacheKey = "idempotency:{$namespace}:{$idempotencyKey}";

        $cached = $store->get($cacheKey);

        if ($cached !== null) {
            return response()->json($cached['body'], $cached['status']);
        }

        $response = $operation();

        $store->put($cacheKey, [
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent(), true),
        ], (int) config('cache.assignment_idempotency_ttl'));

        return $response;
    }
}
