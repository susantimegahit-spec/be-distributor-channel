<?php

namespace App\Modules\VendorPortal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorCredentialsDispatchLog extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_vendor';
    protected $table = 'vendor_credentials_dispatch_logs';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
    }

    protected $fillable = [
        'vendor_user_id',
        'vendor_id',
        'recipient_email',
        'dispatch_channel',
        'dispatch_status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function user()
    {
        return $this->belongsTo(VendorUser::class, 'vendor_user_id');
    }
}
