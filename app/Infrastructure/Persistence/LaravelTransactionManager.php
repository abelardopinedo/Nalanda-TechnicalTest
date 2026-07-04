<?php

namespace App\Infrastructure\Persistence;

use Candidacy\Application\TransactionManager;
use Illuminate\Support\Facades\DB;

class LaravelTransactionManager implements TransactionManager
{
    public function run(callable $operation): mixed
    {
        return DB::transaction($operation);
    }
}
