<?php

namespace Tests\Support;

use Candidacy\Application\TransactionManager;

/**
 * Runs the operation directly with no real transaction, so use cases can be
 * unit-tested against the in-memory repository fake without a database.
 */
final class InMemoryTransactionManager implements TransactionManager
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
