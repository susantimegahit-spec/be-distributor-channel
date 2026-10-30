<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseQuotation extends Model
{
    use HasFactory;

    protected $table = 'purchase_quotations';

    protected $fillable = [
        'number',
        'token',
        'status',
        'title',
        'notes',
        'user_id',
        'valid_until',
    ];

    protected $casts = [
        'valid_until' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseQuotationItem::class, 'purchase_quotation_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(PurchaseQuotationOffer::class, 'purchase_quotation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function generateNumber(): string
    {
        $prefix = 'RFQ-' . date('Ymd') . '-';
        $count = static::whereDate('created_at', date('Y-m-d'))->count() + 1;
        $number = $prefix . str_pad((string)$count, 3, '0', STR_PAD_LEFT);

        while (static::where('number', $number)->exists()) {
            $count++;
            $number = $prefix . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function toApiArray(bool $includeOffers = true): array
    {
        $data = [
            'id'           => $this->id,
            'number'       => $this->number,
            'token'        => $this->token,
            'status'       => $this->status,
            'title'        => $this->title,
            'notes'        => $this->notes,
            'valid_until'  => $this->valid_until?->format('Y-m-d'),
            'createdAt'    => $this->created_at?->toISOString() ?? $this->created_at?->format('Y-m-d H:i:s'),
            'items'        => $this->items->map(fn($item) => $item->toApiArray())->values()->all(),
            'offers_count' => $this->relationLoaded('offers') ? $this->offers->count() : $this->offers()->count(),
        ];

        if ($includeOffers && $this->relationLoaded('offers')) {
            $data['offers'] = $this->offers->map(fn($offer) => $offer->toApiArray())->values()->all();
        }

        return $data;
    }
}

