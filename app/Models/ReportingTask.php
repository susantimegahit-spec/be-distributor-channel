<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportingTask extends Model
{
    use HasFactory;

    protected $table = 'reporting_tasks';

    protected $fillable = [
        'task_id',
        'task_name',
        'space_id',
        'space_name',
        'folder_name',
        'list_name',
        'assignee',
        'timeline',
        'start_date',
        'due_date',
        'priority',
        'task_type',
        'created_by',
        'comment',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date'   => 'datetime',
        'synced_at'  => 'datetime',
    ];
}
