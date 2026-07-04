<?php

namespace App\Listeners;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use Candidacy\Domain\Event\CandidacyRegistered;
use Candidacy\Domain\Event\CandidacyValidated;
use Candidacy\Domain\Event\EvaluatorAssigned;

class WriteDomainEventToActivityLog
{
    public function __construct(private readonly CandidacyMapper $mapper)
    {
    }

    public function handle(CandidacyRegistered|EvaluatorAssigned|CandidacyValidated $event): void
    {
        ActivityLogModel::query()->create($this->mapper->eventToActivityLogAttributes($event));
    }
}
