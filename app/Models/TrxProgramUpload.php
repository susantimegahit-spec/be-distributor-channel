<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrxProgramUpload extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trx_program_upload';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'batch_id',
        'customer_code',
        'customer_name',
        'depo',
        'distributor_customer_code',
        'distributor_customer_name',
        'address',
        'city',
        'district',
        'salesman_name',
        'item_code',
        'item_name',
        'item_category',
        'sell_price_per_kg',
        'qty_kg',
        'customer_type',
        'transaction_type',
        'channel',
        'invoice_no',
        'dpp',
        'diskon_1',
        'diskon_2',
        'diskon_3',
        'diskon_4',
        'diskon_5',
        'ppn',
        'net',
        'transaction_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sell_price_per_kg' => 'decimal:2',
        'qty_kg' => 'decimal:2',
        'dpp' => 'decimal:2',
        'diskon_1' => 'decimal:2',
        'diskon_2' => 'decimal:2',
        'diskon_3' => 'decimal:2',
        'diskon_4' => 'decimal:2',
        'diskon_5' => 'decimal:2',
        'ppn' => 'decimal:2',
        'net' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
    ];

    /**
     * Get the batch that owns the upload record.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrxProgramUploadBatch::class, 'batch_id');
    }

    /**
     * Get the calculation result associated with the upload.
     */
    public function result(): HasOne
    {
        return $this->hasOne(TrxProgramResult::class, 'upload_id');
    }
}
