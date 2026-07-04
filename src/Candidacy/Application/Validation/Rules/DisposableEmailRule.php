<?php

namespace Candidacy\Application\Validation\Rules;

use Candidacy\Application\Validation\CandidacyApplicationData;
use Candidacy\Application\Validation\RuleResult;
use Candidacy\Application\Validation\ValidationRule;

/**
 * Extension example: demonstrates that new rules can be added to the chain
 * by registering the class in config, with no changes to existing rules.
 */
final class DisposableEmailRule implements ValidationRule
{
    /**
     * @var list<string>
     */
    private const DEFAULT_DISPOSABLE_DOMAINS = [
        'mailinator.com',
        'tempmail.com',
        'guerrillamail.com',
        '10minutemail.com',
        'yopmail.com',
    ];

    /**
     * @param list<string> $disposableDomains
     */
    public function __construct(
        private readonly array $disposableDomains = self::DEFAULT_DISPOSABLE_DOMAINS,
    ) {
    }

    public function evaluate(CandidacyApplicationData $application): RuleResult
    {
        $domain = strtolower((string) substr((string) strrchr($application->email, '@'), 1));

        if (in_array($domain, $this->disposableDomains, true)) {
            return RuleResult::fail(self::class, "Disposable email domains such as \"{$domain}\" are not accepted.");
        }

        return RuleResult::pass(self::class);
    }
}
