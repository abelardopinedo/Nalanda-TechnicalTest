<?php

use Candidacy\Application\Validation\Rules\CvMinimumLengthRule;
use Candidacy\Application\Validation\Rules\DisposableEmailRule;
use Candidacy\Application\Validation\Rules\HasCvRule;
use Candidacy\Application\Validation\Rules\MinimumExperienceRule;
use Candidacy\Application\Validation\Rules\ValidEmailRule;

return [

    /*
    |--------------------------------------------------------------------------
    | Candidacy validation rules
    |--------------------------------------------------------------------------
    |
    | Every class listed here is resolved from the container and run by the
    | ValidationChain when a candidacy application is validated. To add a
    | new rule, implement Candidacy\Application\Validation\ValidationRule
    | and list its class here — no existing rule needs to change.
    |
    */

    'rules' => [
        HasCvRule::class,
        ValidEmailRule::class,
        MinimumExperienceRule::class,
        CvMinimumLengthRule::class,
        DisposableEmailRule::class,
    ],

];
