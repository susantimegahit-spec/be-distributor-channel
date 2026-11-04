<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'discount_type',
        'discount_value',
        'nominal_disc',
        'total_price',
        'lead_time',
        'notes',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
    ];

    protected $casts = [
        'quantity'       => 'float',
        'unit_price'     => 'float',
        'discount_value' => 'float',
        'nominal_disc'   => 'float',
        'total_price'    => 'float',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotationOffer::class, 'purchase_quotation_offer_id');
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotationItem::class, 'purchase_quotation_item_id');
    }

    public function getAttachmentUrl(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        if (str_starts_with($this->attachment_path, 'http://') || str_starts_with($this->attachment_path, 'https://')) {
            return $this->attachment_path;
        }

        $s3Configured = !empty(config('filesystems.disks.s3.bucket')) || config('filesystems.disks.s3.driver') === 'local';
        if ($s3Configured) {
            try {
                if (Storage::disk('s3')->exists($this->attachment_path)) {
                    return Storage::disk('s3')->url($this->attachment_path);
                }
            } catch (\Throwable $e) {
                // fallback to public disk
            }
        }

        return Storage::disk('public')->url($this->attachment_path);
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
            'discountType'    => $this->discount_type,
            'discountValue'   => (float) $this->discount_value,
            'nominalDisc'     => (float) $this->nominal_disc,
            'totalPrice'      => (float) $this->total_price,
            'leadTime'        => $this->lead_time,
            'notes'           => $this->notes,
            'attachment'      => $this->getAttachmentUrl(),
            'attachmentName'  => $this->attachment_name,
        ];
    }
}
