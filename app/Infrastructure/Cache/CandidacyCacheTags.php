<?php

namespace App\Infrastructure\Cache;

/**
 * Cache tag names and the dedicated store shared by every candidacy
 * read-cache entry (listing and summary alike), so invalidation and
 * cache-population code always agree on both.
 */
final class CandidacyCacheTags
{
    public const STORE = 'redis';

    public static function candidacy(string $candidacyId): string
    {
        return "candidacy:{$candidacyId}";
    }

    public static function evaluator(string $evaluatorId): string
    {
        return "evaluator:{$evaluatorId}";
    }
}
