<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class CandidacyModel extends Model
{
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
}
