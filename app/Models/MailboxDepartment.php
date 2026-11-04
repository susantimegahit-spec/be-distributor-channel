<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailboxDepartment extends Model
{
    use HasFactory;

    protected $table = 'mailbox_departments';

    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
    ];

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class, 'department_id');
    }

    /**
     * Total storage used by this department in bytes.
     */
    public function getTotalUsageBytesAttribute(): int
    {
        return (int) $this->mailboxes()->sum('current_usage_bytes');
    }

    /**
     * Total quota allocated for this department in bytes.
     */
    public function getTotalQuotaBytesAttribute(): int
    {
        return (int) $this->mailboxes()->sum('quota_bytes');
    }
}
