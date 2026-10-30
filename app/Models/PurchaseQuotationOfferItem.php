<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseQuotationOfferItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_quotation_offer_items';

    protected $fillable = [
        'purchase_quotation_offer_id',
        'purchase_quotation_item_id',
        'item_code',
        'item_name',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'lead_time',
        'notes',
    ];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'total_price'=> 'float',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotationOffer::class, 'purchase_quotation_offer_id');
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotationItem::class, 'purchase_quotation_item_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'              => $this->id,
            'quotationItemId' => $this->purchase_quotation_item_id,
            'itemCode'        => $this->item_code,
            'itemName'        => $this->item_name,
            'quantity'        => (float) $this->quantity,
            'unit'            => $this->unit,
            'unitPrice'       => (float) $this->unit_price,
            'totalPrice'      => (float) $this->total_price,
            'leadTime'        => $this->lead_time,
            'notes'           => $this->notes,
        ];
    }
}

