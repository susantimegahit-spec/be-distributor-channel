<?php

namespace App\Modules\VendorPortal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'pgsql_vendor';
    protected $table = 'vendors';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
    }

    protected $fillable = [
        'vendor_code',
        'vendor_type',
        'company_name',
        'company_email',
        'company_phone',
        'company_npwp',
        'address',
        'city',
        'province',
        'postal_code',
        'pic_name',
        'pic_phone',
        'pic_email',
        'terms_agreed',
        'terms_agreed_at',
        'registration_status',
        'legal_approval_status',
        'legal_approved_by',
        'legal_approved_at',
        'legal_notes',
        'sap_vendor_code',
        'expedition_id',
        'distributor_code',
    ];

    protected $casts = [
        'terms_agreed' => 'boolean',
        'terms_agreed_at' => 'datetime',
        'legal_approved_at' => 'datetime',
    ];

    public function documents()
    {
        return $this->hasMany(VendorDocument::class, 'vendor_id');
    }

    public function users()
    {
        return $this->hasMany(VendorUser::class, 'vendor_id');
    }

    public function approvalHistories()
    {
        return $this->hasMany(VendorApprovalHistory::class, 'vendor_id')->orderBy('created_at', 'desc');
    }

    public function legalApprover()
    {
        return $this->belongsTo(User::class, 'legal_approved_by');
    }

    public function dispatchLogs()
    {
        return $this->hasMany(VendorCredentialsDispatchLog::class, 'vendor_id')->orderBy('created_at', 'desc');
    }
}
