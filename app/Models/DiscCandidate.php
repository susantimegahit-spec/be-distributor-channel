<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscCandidate extends Model
{
    use HasFactory;

    protected $table = 'disc_candidates';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'birth_date',
        'gender',
        'position_applied',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(DiscAssessment::class, 'candidate_id');
    }
}

