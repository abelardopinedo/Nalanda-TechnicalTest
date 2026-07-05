<?php

namespace App\Infrastructure\Providers;

use App\Infrastructure\Persistence\EloquentCandidacyBulkLocker;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use App\Infrastructure\Persistence\LaravelTransactionManager;
use Candidacy\Application\CandidacyBulkLocker;
use Candidacy\Application\TransactionManager;
use Candidacy\Domain\CandidacyRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CandidacyRepository::class, EloquentCandidacyRepository::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(CandidacyBulkLocker::class, EloquentCandidacyBulkLocker::class);
    }
}
