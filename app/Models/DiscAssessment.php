<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscAssessment extends Model
{
    use HasFactory;

    protected $table = 'disc_assessments';

    protected $fillable = [
        'candidate_id',
        'token',
        'status',
        'started_at',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'candidate_id' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(DiscCandidate::class, 'candidate_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DiscAnswer::class, 'assessment_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(DiscAssessmentResult::class, 'assessment_id');
    }
}

