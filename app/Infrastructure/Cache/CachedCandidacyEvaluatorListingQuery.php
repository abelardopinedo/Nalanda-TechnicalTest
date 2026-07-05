<?php

namespace App\Infrastructure\Cache;

use App\Infrastructure\Query\CandidacyEvaluatorListingQuery;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Redis-backed read cache in front of CandidacyEvaluatorListingQuery, keyed
 * by the full sort+direction+filters+perPage+page combination.
 *
 * Which evaluators end up on a given page can't be known before the query
 * actually runs, so a page is tagged only *after* it's computed: one
 * "evaluator:{id}" tag (CandidacyCacheTags::evaluator()) per evaluator
 * present in the result. That tag list is then cached alongside the page
 * itself, under a plain untagged "registry" key, so a later read can look up
 * which tags to re-check without having to recompute first. A miss on
 * either the registry or the tagged value is treated as a cold key.
 *
 * This makes invalidation selective: assigning a candidacy to evaluator X
 * only evicts the pages that actually contained evaluator X, leaving cached
 * pages for every other evaluator untouched.
 *
 * Cache values are stored as plain arrays, not the LengthAwarePaginator (and
 * its Collection of stdClass rows) itself: Laravel's cache stores refuse to
 * unserialize arbitrary objects by default (config('cache.serializable_classes'),
 * a hardening against cache-poisoning object-injection), so a cached object
 * would silently come back as a useless __PHP_Incomplete_Class. Storing only
 * scalars/arrays avoids depending on that allow-list at all.
 */
final class CachedCandidacyEvaluatorListingQuery
{
    private const TTL_SECONDS = 60;

    private const LOCK_TTL_SECONDS = 10;

    /**
     * Whether the most recent paginate() call was served from cache (true)
     * or required a fresh recompute (false), for callers that need to
     * reflect it (e.g. an X-Cache response header). Reset on every call, so
     * it only ever describes the call that just returned.
     */
    private bool $lastOutcomeWasHit = false;

    public function __construct(
        private readonly CandidacyEvaluatorListingQuery $query,
        private readonly int $lockWaitSeconds = 5,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(
        string $sort = CandidacyEvaluatorListingQuery::DEFAULT_SORT,
        string $direction = CandidacyEvaluatorListingQuery::DEFAULT_DIRECTION,
        array $filters = [],
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);
        $key = $this->cacheKey($sort, $direction, $filters, $perPage, $page);
        $registryKey = "listing-tags:{$key}";

        $cached = $this->readThroughRegistry($store, $key, $registryKey);

        if ($cached !== null) {
            $this->lastOutcomeWasHit = true;

            return $this->fromCacheable($cached);
        }

        $recompute = function () use ($store, $key, $registryKey, $sort, $direction, $filters, $perPage, $page): LengthAwarePaginator {
            // Another worker may have finished computing this exact key
            // while we were waiting for the lock.
            $cached = $this->readThroughRegistry($store, $key, $registryKey);

            if ($cached !== null) {
                $this->lastOutcomeWasHit = true;

                return $this->fromCacheable($cached);
            }

            $this->lastOutcomeWasHit = false;
            $result = $this->query->paginate($sort, $direction, $filters, $perPage, $page);

            $evaluatorTags = $result->getCollection()
                ->pluck('evaluator_id')
                ->filter()
                ->unique()
                ->map(static fn (string $evaluatorId): string => CandidacyCacheTags::evaluator($evaluatorId))
                ->values()
                ->all();

            $store->tags($evaluatorTags)->put($key, $this->toCacheable($result), self::TTL_SECONDS);
            $store->put($registryKey, $evaluatorTags, self::TTL_SECONDS);

            return $result;
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
     * @return array{items: list<array<string, mixed>>, total: int, perPage: int, currentPage: int}|null
     */
    private function readThroughRegistry(Repository $store, string $key, string $registryKey): ?array
    {
        $tags = $store->get($registryKey);

        if (! is_array($tags)) {
            return null;
        }

        return $store->tags($tags)->get($key);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, perPage: int, currentPage: int}
     */
    private function toCacheable(LengthAwarePaginator $paginator): array
    {
        return [
            'items' => $paginator->getCollection()->map(static fn (object $row): array => (array) $row)->all(),
            'total' => $paginator->total(),
            'perPage' => $paginator->perPage(),
            'currentPage' => $paginator->currentPage(),
        ];
    }

    /**
     * @param  array{items: list<array<string, mixed>>, total: int, perPage: int, currentPage: int}  $cached
     */
    private function fromCacheable(array $cached): LengthAwarePaginator
    {
        $items = array_map(static fn (array $row): object => (object) $row, $cached['items']);

        return new LengthAwarePaginator(
            $items,
            $cached['total'],
            $cached['perPage'],
            $cached['currentPage'],
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function cacheKey(string $sort, string $direction, array $filters, int $perPage, int $page): string
    {
        ksort($filters);

        return 'candidacy-listing:'.md5(json_encode(compact('sort', 'direction', 'filters', 'perPage', 'page')));
    }
}
