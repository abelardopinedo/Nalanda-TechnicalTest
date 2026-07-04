<?php

namespace Candidacy\Application;

/**
 * Port for atomically running a unit of work. Kept framework-free so
 * application use cases never depend on the infrastructure that fulfils it.
 */
interface TransactionManager
{
    /**
     * @template TReturn
     * @param callable(): TReturn $operation
     * @return TReturn
     */
    public function run(callable $operation): mixed;
}
