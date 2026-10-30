<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseQuotationOffer extends Model
{
    use HasFactory;

    protected $table = 'purchase_quotation_offers';

    protected $fillable = [
        'purchase_quotation_id',
        'company_name',
        'pic_name',
        'email',
        'phone',
        'notes',
        'status',
        'total_price',
        'submitted_at',
        'ip_address',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'total_price'  => 'float',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class, 'purchase_quotation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseQuotationOfferItem::class, 'purchase_quotation_offer_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'quotationId' => $this->purchase_quotation_id,
            'vendor'      => [
                'company' => $this->company_name,
                'name'    => $this->pic_name,
                'email'   => $this->email,
                'phone'   => $this->phone,
                'notes'   => $this->notes,
            ],
            'status'      => $this->status,
            'totalPrice'  => (float) $this->total_price,
            'submittedAt' => $this->submitted_at?->toISOString() ?? $this->created_at?->toISOString(),
            'items'       => $this->items->map(fn($item) => $item->toApiArray())->values()->all(),
        ];
    }
}

