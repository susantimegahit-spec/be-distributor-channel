<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrisEmployee extends CorporateModel
{
    protected $table = 'hris_employees';

    protected $fillable = [
        'nik',
        'user_id',
        'full_name',
        'nickname',
        'email_office',
        'phone_number',
        'telegram_chat_id',
        'department_id',
        'division_id',
        'position_id',
        'direct_supervisor_id',
        'employment_status',
        'join_date',
        'resign_date',
        'is_active',
        'avatar_url',
    ];

    protected $casts = [
        'join_date'   => 'date',
        'resign_date' => 'date',
        'is_active'   => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrisDepartment::class, 'department_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(HrisDivision::class, 'division_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrisPosition::class, 'position_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'direct_supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(HrisEmployee::class, 'direct_supervisor_id');
    }

    public function spaceMemberships(): HasMany
    {
        return $this->hasMany(TmSpaceMember::class, 'employee_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(TmTaskAssignee::class, 'employee_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(TmTask::class, 'created_by_employee_id');
    }

    public function timeTrackings(): HasMany
    {
        return $this->hasMany(TmTaskTimeTracking::class, 'employee_id');
    }
}
