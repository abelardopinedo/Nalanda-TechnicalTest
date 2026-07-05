<?php

namespace App\Infrastructure\Cache;

use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\Eloquent\EvaluatorModel;
use App\Infrastructure\Query\CandidacySummaryData;
use App\Infrastructure\Query\CandidacySummaryQuery;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed read cache in front of CandidacySummaryQuery, keyed and
 * tagged per candidacy id so a single domain event (registration, validation
 * outcome, evaluator assignment) can evict exactly that candidacy's cached
 * summary via CandidacyCacheTags::candidacy(), without touching any other.
 *
 * The tag is known upfront from the candidacy id argument itself (unlike the
 * listing cache, whose tags depend on the query result), so this reads and
 * writes through Cache::tags() directly with no extra indirection.
 *
 * Cache values are stored as plain arrays of raw model attributes, not the
 * CandidacySummaryData object itself: Laravel's cache stores refuse to
 * unserialize arbitrary objects by default (config('cache.serializable_classes'),
 * a hardening against cache-poisoning object-injection), so any cached
 * object silently comes back as a useless __PHP_Incomplete_Class. Storing
 * only scalars/arrays avoids depending on that allow-list at all.
 */
final class CachedCandidacySummaryQuery
{
    private const TTL_SECONDS = 60;

    private const LOCK_TTL_SECONDS = 10;

    /**
     * Whether the most recent forCandidacy() call was served from cache
     * (true) or required a fresh recompute (false), for callers that need
     * to reflect it (e.g. an X-Cache response header). Reset on every call,
     * so it only ever describes the call that just returned.
     */
    private bool $lastOutcomeWasHit = false;

    public function __construct(
        private readonly CandidacySummaryQuery $query,
        private readonly int $lockWaitSeconds = 5,
    ) {
    }

    public function forCandidacy(string $candidacyId): ?CandidacySummaryData
    {
        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);
        $key = "candidacy-summary:{$candidacyId}";
        $tagged = $store->tags([CandidacyCacheTags::candidacy($candidacyId)]);

        $cached = $tagged->get($key);

        if ($cached !== null) {
            $this->lastOutcomeWasHit = true;

            return $this->fromCacheable($cached);
        }

        $recompute = function () use ($tagged, $key, $candidacyId): ?CandidacySummaryData {
            // Another worker may have finished computing this exact key
            // while we were waiting for the lock.
            $cached = $tagged->get($key);

            if ($cached !== null) {
                $this->lastOutcomeWasHit = true;

                return $this->fromCacheable($cached);
            }

            $this->lastOutcomeWasHit = false;
            $data = $this->query->forCandidacy($candidacyId);

            if ($data !== null) {
                $tagged->put($key, $this->toCacheable($data), self::TTL_SECONDS);
            }

            return $data;
        };

        $lock = $store->lock("lock:{$key}", self::LOCK_TTL_SECONDS);

        try {
            return $lock->block($this->lockWaitSeconds, $recompute);
        } catch (LockTimeoutException) {
            // Another worker is taking unusually long to finish the same
            // recompute: degrade gracefully by computing directly rather
            // than failing the request.
            return $recompute();
        }
    }

    /**
     * @see self::$lastOutcomeWasHit
     */
    public function lastOutcomeWasHit(): bool
    {
        return $this->lastOutcomeWasHit;
    }

    /**
     * @return array{candidacy: array<string, mixed>, validationEntry: array<string, mixed>|null, evaluator: array<string, mixed>|null}
     */
    private function toCacheable(CandidacySummaryData $data): array
    {
        return [
            'candidacy' => $data->candidacy->getAttributes(),
            'validationEntry' => $data->validationEntry?->getAttributes(),
            'evaluator' => $data->evaluator?->getAttributes(),
        ];
    }

    /**
     * @param  array{candidacy: array<string, mixed>, validationEntry: array<string, mixed>|null, evaluator: array<string, mixed>|null}  $cached
     */
    private function fromCacheable(array $cached): CandidacySummaryData
    {
        return new CandidacySummaryData(
            (new CandidacyModel())->setRawAttributes($cached['candidacy'], true),
            $cached['validationEntry'] !== null
                ? (new ActivityLogModel())->setRawAttributes($cached['validationEntry'], true)
                : null,
            $cached['evaluator'] !== null
                ? (new EvaluatorModel())->setRawAttributes($cached['evaluator'], true)
                : null,
        );
    }
}
