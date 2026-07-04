<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Database\Factories\EvaluatorModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluatorModel extends Model
{
    use HasFactory;

    protected $table = 'evaluators';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
    ];

    protected static function newFactory(): EvaluatorModelFactory
    {
        return EvaluatorModelFactory::new();
    }
}
