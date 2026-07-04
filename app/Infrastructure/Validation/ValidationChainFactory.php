<?php

namespace App\Infrastructure\Validation;

use Candidacy\Application\Validation\ValidationChain;
use Illuminate\Contracts\Container\Container;

final class ValidationChainFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    public function make(): ValidationChain
    {
        $ruleClasses = config('candidacy_validation.rules', []);

        $rules = array_map(
            fn (string $ruleClass) => $this->container->make($ruleClass),
            $ruleClasses,
        );

        return new ValidationChain($rules);
    }
}
