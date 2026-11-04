<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class DiscCandidate extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'disc_candidates';

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'avatar',
        'phone',
        'birth_date',
        'gender',
        'position_applied',
        'biodata',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'biodata' => 'array',
        'password' => 'hashed',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(DiscAssessment::class, 'candidate_id');
    }
}


