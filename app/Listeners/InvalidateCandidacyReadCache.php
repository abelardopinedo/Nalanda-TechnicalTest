<?php

namespace App\Listeners;

use App\Infrastructure\Cache\CandidacyCacheTags;
use Candidacy\Domain\Event\CandidacyRegistered;
use Candidacy\Domain\Event\CandidacyValidated;
use Candidacy\Domain\Event\EvaluatorAssigned;
use Illuminate\Support\Facades\Cache;

/**
 * Evicts exactly the cached read-side entries a domain event can have made
 * stale, via cache tags, rather than flushing the whole read cache:
 *
 *  - every event evicts this candidacy's cached summary (CandidacyCacheTags::candidacy())
 *  - an assignment additionally evicts every cached listing page that
 *    contains that evaluator (CandidacyCacheTags::evaluator())
 *
 * Registration and a validation outcome never touch the listing cache: a
 * candidacy only appears there once assigned, so there is nothing to evict
 * for those two events beyond the summary.
 */
class InvalidateCandidacyReadCache
{
    public function handle(CandidacyRegistered|EvaluatorAssigned|CandidacyValidated $event): void
    {
        /** @var \Illuminate\Cache\Repository $store */
        $store = Cache::store(CandidacyCacheTags::STORE);

        $store->tags([CandidacyCacheTags::candidacy($event->candidacyId)])->flush();

        if ($event instanceof EvaluatorAssigned) {
            $store->tags([CandidacyCacheTags::evaluator($event->evaluatorId)])->flush();
        }
    }
}
