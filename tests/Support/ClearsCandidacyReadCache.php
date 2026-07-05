<?php

namespace Tests\Support;

use App\Infrastructure\Cache\CandidacyCacheTags;
use Illuminate\Support\Facades\Cache;

/**
 * RefreshDatabase resets MySQL between tests but has no bearing on Redis:
 * without this, a cached listing/summary entry written by one test (e.g.
 * the default, unfiltered listing page) leaks into the next test that
 * happens to hit the same cache key. Used only by tests that exercise the
 * cached listing/summary endpoints, not the whole suite.
 */
trait ClearsCandidacyReadCache
{
    protected function tearDown(): void
    {
        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);
        $store->flush();

        parent::tearDown();
    }
}
