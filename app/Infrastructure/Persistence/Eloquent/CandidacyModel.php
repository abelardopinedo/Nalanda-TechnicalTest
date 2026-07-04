<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Database\Factories\CandidacyModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidacyModel extends Model
{
    use HasFactory;

    protected $table = 'candidacies';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'full_name',
        'email',
        'years_of_experience',
        'cv_text',
        'status',
        'evaluator_id',
        'assigned_at',
    ];

    protected $casts = [
        'years_of_experience' => 'integer',
        'assigned_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): CandidacyModelFactory
    {
        return CandidacyModelFactory::new();
    }
}
