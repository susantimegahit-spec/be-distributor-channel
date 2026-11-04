<?php

namespace App\Modules\VendorPortal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class VendorApprovalHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $connection = 'pgsql_vendor';
    protected $table = 'vendor_approval_histories';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
    }

    protected $fillable = [
        'vendor_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'actor_name',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
