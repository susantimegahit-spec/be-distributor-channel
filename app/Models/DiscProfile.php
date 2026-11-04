<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscProfile extends Model
{
    use HasFactory;

    protected $table = 'disc_profiles';

    protected $fillable = [
        'profile_number',
        'title',
        'dimension_code',
        'characteristics',
    ];

    protected $casts = [
        'profile_number' => 'integer',
        'characteristics' => 'array',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(DiscAssessmentResult::class, 'profile_id');
    }
}

