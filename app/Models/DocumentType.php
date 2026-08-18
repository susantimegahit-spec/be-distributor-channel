<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentType extends Model
{
    use HasFactory;

    protected $table = 'document_types';

    protected $fillable = [
        'code',
        'name',
        'sap_object_type',
        'module',
        'header_source',
        'line_source',
        'adapter_class',
        'description',
        'icon_path',
        'attachment_path',
        'attachment_name',
        'is_active',
    ];

    protected $casts = [
        'sap_object_type' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'icon_url',
        'attachment_url',
    ];

    public function getIconUrlAttribute(): ?string
    {
        if ($this->icon_path) {
            return asset('storage/' . $this->icon_path);
        }
        return null;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if ($this->attachment_path) {
            return asset('storage/' . $this->attachment_path);
        }
        return null;
    }

    public function schemas(): HasMany
    {
        return $this->hasMany(DocumentSchema::class, 'document_type_id');
    }

    public function activeSchema(): HasOne
    {
        return $this->hasOne(DocumentSchema::class, 'document_type_id')->where('is_active', true)->latest('version');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'document_type_id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class, 'document_type_id');
    }
}
