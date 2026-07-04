<?php

namespace Candidacy\Application;

/**
 * Port for generating the consolidated candidacy/evaluator listing report
 * and persisting it to storage. Kept framework-free so the application
 * layer never depends on which spreadsheet library fulfils it — swap the
 * bound implementation (see App\Infrastructure\Report) without touching
 * anything that depends on this interface.
 */
interface ReportGenerator
{
    /**
     * Generates the report and stores it at $path on the given filesystem
     * disk (as configured in config/filesystems.php). $filters narrows the
     * consolidated listing the same way the paginated listing's filters do —
     * both are whitelisted against the exact same apiField => dbColumn map,
     * so the caller must never pass anything that map hasn't already vetted.
     *
     * @param  array<string, string>  $filters
     */
    public function generate(string $path, string $disk, array $filters = []): void;
}
