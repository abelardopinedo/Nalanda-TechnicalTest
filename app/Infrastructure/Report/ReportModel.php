<?php

namespace App\Infrastructure\Report;

use Database\Factories\ReportModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * Pure infrastructure/read metadata for tracking the report generation
 * flow (status, idempotency, re-downloadability) — not a domain entity, so
 * it gets no domain/mapper treatment the way Candidacy does.
 */
class ReportModel extends Model
{
    use HasFactory;

    public const DISK = 'local';

    protected $table = 'reports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'requested_by_email',
        'status',
        'file_path',
        'idempotency_key',
        'error_message',
        'filters_snapshot',
        'completed_at',
    ];

    protected $casts = [
        'status' => ReportStatus::class,
        'filters_snapshot' => 'array',
        'completed_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): ReportModelFactory
    {
        return ReportModelFactory::new();
    }

    /**
     * Shapes this report's current state for the API — the same shape
     * whether reached via POST /reports (idempotent replay of an existing
     * report) or GET /reports/{id}.
     *
     * @return array<string, mixed>
     */
    public function toStatusPayload(): array
    {
        return match ($this->status) {
            ReportStatus::PENDING => [
                'report_id' => $this->id,
                'status' => ReportStatus::PENDING->value,
            ],
            ReportStatus::COMPLETED => [
                'report_id' => $this->id,
                'status' => ReportStatus::COMPLETED->value,
                'file_path' => $this->file_path,
                'download_url' => $this->downloadUrl(),
            ],
            ReportStatus::FAILED => [
                'report_id' => $this->id,
                'status' => ReportStatus::FAILED->value,
                'error_message' => $this->error_message,
            ],
        };
    }

    /**
     * The "local" disk is private (no `visibility: public`), so its serve
     * route (storage.local) requires a valid signed URL — a plain
     * Storage::url() is unsigned and gets rejected. ServeFile checks
     * hasValidRelativeSignature() specifically (not hasValidSignature()),
     * which verifies the signature against the *relative* path+query only
     * (host/scheme ignored) — so this must be signed with absolute: false,
     * or the signature never matches and every download 403s. The result
     * is still turned into an absolute, clickable link via url() for the
     * email/API response; that's safe because prepending the host doesn't
     * change the path or query string the signature was computed over.
     * Shared by toStatusPayload() and GenerateReportJob's email so both
     * construct this the one, correct way.
     */
    public function downloadUrl(): string
    {
        $relativeSignedPath = URL::temporarySignedRoute(
            'storage.'.self::DISK,
            now()->addDays(7),
            ['path' => $this->file_path],
            absolute: false,
        );

        return url($relativeSignedPath);
    }
}
