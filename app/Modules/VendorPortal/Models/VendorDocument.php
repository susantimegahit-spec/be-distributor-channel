<?php

namespace App\Modules\VendorPortal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class VendorDocument extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_vendor';
    protected $table = 'vendor_documents';

    public function getConnectionName()
    {
        return config('database.default') === 'sqlite' ? 'sqlite' : 'pgsql_vendor';
    }

    protected $fillable = [
        'vendor_id',
        'document_type',
        'document_number',
        'file_path',
        'file_name',
        'file_size',
        'file_mime',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'file_url',
    ];

    /**
     * Get the public asset URL for the uploaded document file.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        return asset('storage/' . $this->file_path);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
