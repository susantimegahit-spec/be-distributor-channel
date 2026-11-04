<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscAnswer extends Model
{
    use HasFactory;

    protected $table = 'disc_answers';

    protected $fillable = [
        'assessment_id',
        'question_number',
        'p_choice',
        'k_choice',
    ];

    protected $casts = [
        'assessment_id' => 'integer',
        'question_number' => 'integer',
        'p_choice' => 'integer',
        'k_choice' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DiscAssessment::class, 'assessment_id');
    }
}

