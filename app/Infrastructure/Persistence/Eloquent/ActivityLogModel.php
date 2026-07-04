<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ActivityLogModel extends Model
{
    protected $table = 'activity_log';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Append-only: rows are never updated once written.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'candidacy_id',
        'evaluator_id',
        'action',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'immutable_datetime',
    ];

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('activity_log is append-only; existing entries cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('activity_log is append-only; entries cannot be deleted.');
    }
}
