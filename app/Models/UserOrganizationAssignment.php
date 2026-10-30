<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOrganizationAssignment extends Model
{
    use HasFactory;

    protected $table = 'user_organization_assignments';

    protected $fillable = [
        'user_id',
        'type',
        'value',
        'name',
    ];

    /**
     * Get the user that owns the organization assignment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
