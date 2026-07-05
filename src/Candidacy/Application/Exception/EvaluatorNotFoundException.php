<?php

namespace Candidacy\Application\Exception;

use RuntimeException;

final class EvaluatorNotFoundException extends RuntimeException
{
    public function __construct(string $evaluatorId)
    {
        parent::__construct("Evaluator \"{$evaluatorId}\" was not found.");
    }
}
