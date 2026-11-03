<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscQuestion extends Model
{
    use HasFactory;

    protected $table = 'disc_questions';

    protected $fillable = [
        'question_number',
        'statement_1',
        'statement_2',
        'statement_3',
        'statement_4',
        'p_key_1',
        'p_key_2',
        'p_key_3',
        'p_key_4',
        'k_key_1',
        'k_key_2',
        'k_key_3',
        'k_key_4',
        'updated_by',
    ];

    protected $casts = [
        'question_number' => 'integer',
        'updated_by' => 'integer',
    ];

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get candidate-safe format (stripping scoring keys).
     */
    public function toCandidateSafeArray(): array
    {
        return [
            'question_number' => $this->question_number,
            'statements' => [
                ['choice' => 1, 'text' => $this->statement_1],
                ['choice' => 2, 'text' => $this->statement_2],
                ['choice' => 3, 'text' => $this->statement_3],
                ['choice' => 4, 'text' => $this->statement_4],
            ],
        ];
    }
}

