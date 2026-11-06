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

    public const STATUS_PRESCREEN        = 'prescreen';
    public const STATUS_SHORTLIST        = 'shortlist';
    public const STATUS_REJECT           = 'reject';
    public const STATUS_HR_INTERVIEW     = 'hr_interview';
    public const STATUS_USER_INTERVIEW   = 'user_interview';
    public const STATUS_PSIKOTES         = 'psikotes';
    public const STATUS_FINAL_INTERVIEW  = 'final_interview';
    public const STATUS_OFFERING         = 'offering';
    public const STATUS_JOIN             = 'join';

    public const ALL_STATUSES = [
        self::STATUS_PRESCREEN,
        self::STATUS_SHORTLIST,
        self::STATUS_REJECT,
        self::STATUS_HR_INTERVIEW,
        self::STATUS_USER_INTERVIEW,
        self::STATUS_PSIKOTES,
        self::STATUS_FINAL_INTERVIEW,
        self::STATUS_OFFERING,
        self::STATUS_JOIN,
    ];

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
        'status',
        'biodata',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'biodata'    => 'array',
        'password'   => 'hashed',
    ];

    /**
     * Check if candidate status is allowed to fill biodata and take assessments (MDT, DISC).
     * Only shortlisted candidates (and onwards) can proceed. Prescreen and reject are blocked.
     */
    public function canAccessAssessmentsAndBiodata(): bool
    {
        return in_array($this->status, [
            self::STATUS_SHORTLIST,
            self::STATUS_HR_INTERVIEW,
            self::STATUS_USER_INTERVIEW,
            self::STATUS_PSIKOTES,
            self::STATUS_FINAL_INTERVIEW,
            self::STATUS_OFFERING,
            self::STATUS_JOIN,
        ], true);
    }

    /**
     * Rejection/access denial explanation for prescreen or rejected candidates.
     */
    public function getAssessmentAccessDeniedMessage(): string
    {
        if ($this->status === self::STATUS_REJECT) {
            return "Status akun Anda adalah 'reject'. Anda tidak dapat mengisi biodata atau mengerjakan asesmen.";
        }

        return "Status akun Anda masih '" . ($this->status ?? self::STATUS_PRESCREEN) . "'. Pengisian biodata dan pengerjaan asesmen hanya dapat diakses setelah akun Anda berstatus 'shortlist' oleh tim HR.";
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DiscAssessment::class, 'candidate_id');
    }
}


