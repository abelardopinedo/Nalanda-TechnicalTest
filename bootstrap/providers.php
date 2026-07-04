<?php

use App\Infrastructure\Providers\EventServiceProvider;
use App\Infrastructure\Providers\RepositoryServiceProvider;
use App\Infrastructure\Providers\ValidationServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    ValidationServiceProvider::class,
    EventServiceProvider::class,
];
