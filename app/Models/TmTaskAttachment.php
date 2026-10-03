<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmTaskAttachment extends CorporateModel
{
    protected $table = 'tm_task_attachments';

    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'file_name',
        'file_path',
        'file_size_bytes',
        'mime_type',
        'uploaded_by_employee_id',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TmTask::class, 'task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'uploaded_by_employee_id');
    }
}
