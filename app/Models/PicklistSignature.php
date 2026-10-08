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

        return Storage::disk('public')->url($this->signature_path);
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
