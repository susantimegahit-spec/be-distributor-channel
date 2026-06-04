<?php

namespace App\Modules\AuditLog\Services;

use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an action to the database.
     *
     * @param  int|null  $userId
     * @param  string  $action
     * @param  string|null  $description
     * @return AuditLog
     */
    public function log(?int $userId, string $action, ?string $description = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }
}
