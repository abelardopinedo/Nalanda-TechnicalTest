<?php

namespace App\Infrastructure\Providers;

use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use Candidacy\Domain\CandidacyRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidacyRepository::class, EloquentCandidacyRepository::class);
    }
}
