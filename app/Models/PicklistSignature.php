<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PicklistSignature extends Model
{
    use HasFactory;

    public const TYPE_CHECKER    = 'checker';
    public const TYPE_DRIVER     = 'driver';
    public const TYPE_SUPERVISOR = 'supervisor';

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql_ekspedisi';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ekspedisi.picklist_signatures';

    /**
     * Get the table associated with the model (stripping schema in sqlite).
     */
    public function getTable(): string
    {
        $table = parent::getTable();
        if ($this->getConnection()->getDriverName() === 'sqlite') {
            $parts = explode('.', $table);
            return end($parts);
        }
        return $table;
    }

    protected $fillable = [
        'picklist_id',
        'signer_type',
        'signer_role_title',
        'signer_name',
        'signature_path',
        'signed_at',
        'sort_order',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'picklist_id' => 'integer',
        'signed_at'   => 'datetime',
        'sort_order'  => 'integer',
        'created_by'  => 'integer',
    ];

    protected $appends = [
        'signature_url',
        'signature_data',
        'signature',
    ];

    /**
     * Public URL accessor for signature image.
     */
    public function getSignatureUrlAttribute(): ?string
    {
        if (empty($this->signature_path)) {
            return null;
        }

        if (str_starts_with($this->signature_path, 'http://') || str_starts_with($this->signature_path, 'https://')) {
            return $this->signature_path;
        }

        if (str_starts_with($this->signature_path, 'data:')) {
            return $this->signature_path;
        }

        $s3Bucket = config('filesystems.disks.s3.bucket');
        if (!empty($s3Bucket)) {
            try {
                if (Storage::disk('s3')->exists($this->signature_path)) {
                    return Storage::disk('s3')->url($this->signature_path);
                }
            } catch (\Throwable) {
                // If S3 existence check fails, fall through to preview route or public disk
            }
        }

        if ($this->picklist_id && $this->id) {
            return url("api/distributor-channel/v1/logistic/picklists/{$this->picklist_id}/signatures/{$this->id}/preview");
        }

        return Storage::disk('public')->url($this->signature_path);
    }

    /**
     * Base64 Data URI accessor for inline signature image preview.
     */
    public function getSignatureDataAttribute(): ?string
    {
        if (empty($this->signature_path)) {
            return null;
        }

        if (str_starts_with($this->signature_path, 'data:') || str_starts_with($this->signature_path, '<svg')) {
            return $this->signature_path;
        }

        if (str_starts_with($this->signature_path, 'http://') || str_starts_with($this->signature_path, 'https://')) {
            return $this->signature_path;
        }

        try {
            $binary = null;
            $mime = 'image/png';

            if (config('filesystems.disks.s3.bucket') && Storage::disk('s3')->exists($this->signature_path)) {
                $binary = Storage::disk('s3')->get($this->signature_path);
                $mime = Storage::disk('s3')->mimeType($this->signature_path) ?: 'image/png';
            } elseif (Storage::disk('public')->exists($this->signature_path)) {
                $binary = Storage::disk('public')->get($this->signature_path);
                $mime = Storage::disk('public')->mimeType($this->signature_path) ?: 'image/png';
            } elseif (file_exists(storage_path('app/public/' . $this->signature_path))) {
                $full = storage_path('app/public/' . $this->signature_path);
                $binary = file_get_contents($full);
                $mime = mime_content_type($full) ?: 'image/png';
            } elseif (file_exists(public_path('storage/' . $this->signature_path))) {
                $full = public_path('storage/' . $this->signature_path);
                $binary = file_get_contents($full);
                $mime = mime_content_type($full) ?: 'image/png';
            }

            if ($binary !== null) {
                return "data:{$mime};base64," . base64_encode($binary);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to encode signature data for #{$this->id}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Alias attribute for signature preview.
     */
    public function getSignatureAttribute(): ?string
    {
        return $this->signature_data ?: $this->signature_url;
    }

    /**
     * Relationship to Picklist.
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(Picklist::class, 'picklist_id');
    }

    /**
     * Relationship to User who recorded/created the signature.
     */
    public function creator(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', User::class);
        $instance = new $userModel();
        $instance->setConnection(config('database.default'));
        return $this->newBelongsTo($instance->newQuery(), $this, 'created_by', 'id', 'creator');
    }
}
