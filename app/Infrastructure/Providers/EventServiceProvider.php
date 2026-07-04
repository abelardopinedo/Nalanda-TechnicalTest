<?php

namespace App\Infrastructure\Providers;

use App\Listeners\WriteDomainEventToActivityLog;
use Candidacy\Domain\Event\CandidacyRegistered;
use Candidacy\Domain\Event\EvaluatorAssigned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CandidacyRegistered::class, WriteDomainEventToActivityLog::class);
        Event::listen(EvaluatorAssigned::class, WriteDomainEventToActivityLog::class);
    }
}
