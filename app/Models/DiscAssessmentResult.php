<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscAssessmentResult extends Model
{
    use HasFactory;

    protected $table = 'disc_assessment_results';

    protected $fillable = [
        'assessment_id',
        'p_counts',
        'k_counts',
        'net_counts',
        'chart_scores',
        'profile_id',
        'profile_number',
        'profile_title',
        'dimension_code',
        'matched_rule_index',
        'status',
    ];

    protected $casts = [
        'assessment_id' => 'integer',
        'p_counts' => 'array',
        'k_counts' => 'array',
        'net_counts' => 'array',
        'chart_scores' => 'array',
        'profile_id' => 'integer',
        'profile_number' => 'integer',
        'matched_rule_index' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DiscAssessment::class, 'assessment_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DiscProfile::class, 'profile_id');
    }
}

