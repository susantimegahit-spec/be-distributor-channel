<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseQuotationItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_quotation_items';

    protected $fillable = [
        'purchase_quotation_id',
        'request_id',
        'request_number',
        'line_key',
        'item_code',
        'item_name',
        'quantity',
        'unit',
        'warehouse',
        'required_date',
        'notes',
    ];

    protected $casts = [
        'quantity'      => 'float',
        'required_date' => 'date',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class, 'purchase_quotation_id');
    }

    public function offerItems(): HasMany
    {
        return $this->hasMany(PurchaseQuotationOfferItem::class, 'purchase_quotation_item_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'requestId'     => $this->request_id,
            'requestNumber' => $this->request_number,
            'itemCode'      => $this->item_code,
            'itemName'      => $this->item_name,
            'quantity'      => (float) $this->quantity,
            'unit'          => $this->unit,
            'warehouse'     => $this->warehouse,
            'requiredDate'  => $this->required_date?->format('Y-m-d'),
            'notes'         => $this->notes,
        ];
    }
}

