<?php

namespace App\Infrastructure\Report;

/**
 * Status of a reports row. Pure infrastructure/read metadata for tracking
 * the generation flow — not a domain concept, so it lives alongside
 * ReportModel rather than under src/.
 */
enum ReportStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
