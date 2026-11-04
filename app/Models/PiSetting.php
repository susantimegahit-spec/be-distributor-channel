<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PiSetting extends Model
{
    use HasFactory;

    protected $table = 'pi_settings';

    protected $fillable = [
        'user_id',
        'document_tag',
        'signer_name',
        'signer_title',
        'signature_path',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    protected $appends = [
        'signature_url',
    ];

    /**
     * Get the signature image URL.
     */
    public function getSignatureUrlAttribute(): ?string
    {
        if ($this->signature_path) {
            return asset('storage/' . $this->signature_path);
        }
        return null;
    }
}
