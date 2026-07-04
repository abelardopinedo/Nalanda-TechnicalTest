<?php

namespace App\Infrastructure\Providers;

use App\Infrastructure\Validation\ValidationChainFactory;
use Candidacy\Application\Validation\ValidationChain;
use Illuminate\Support\ServiceProvider;

class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/candidacy_validation.php', 'candidacy_validation');

        $this->app->singleton(
            ValidationChain::class,
            static fn ($app) => $app->make(ValidationChainFactory::class)->make(),
        );
    }
}
