<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'menu',
        'is_active',
        'approval_id',
    ];

    protected function casts(): array
    {
        return [
            'menu' => 'array',
            'is_active' => 'boolean',
            'approval_id' => 'integer',
        ];
    }

    /**
     * Get the role associated with the menu.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the approval stage associated with the role menu.
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MasterApproval::class, 'approval_id');
    }
}
