<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrxProgramResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trx_program_result';

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
        'upload_id',
        'program_id',
        'customer_code',
        'customer_name',
        'item_code',
        'item_name',
        'qty_kg',
        'sell_price_per_kg',
        'harga_program_per_kg',
        'diskon_per_kg',
        'total_diskon',
        'transaction_date',
        'status',
        'is_verified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'qty_kg' => 'decimal:2',
        'sell_price_per_kg' => 'decimal:2',
        'harga_program_per_kg' => 'decimal:2',
        'diskon_per_kg' => 'decimal:2',
        'total_diskon' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
        'is_verified' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'customer_type',
        'desc_status',
        'depo',
        'distributor_customer_code',
        'distributor_customer_name',
        'address',
        'city',
        'district',
        'salesman_name',
        'item_category',
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
    ];

    /**
     * Get the customer type from the related upload.
     */
    public function getCustomerTypeAttribute(): ?string
    {
        return $this->upload ? $this->upload->customer_type : null;
    }

    /**
     * Get the description status based on the result status.
     */
    public function getDescStatusAttribute(): string
    {
        return $this->status === 'VALID_PROGRAM' ? 'VALID' : 'NOT VALID';
    }

    public function getDepoAttribute(): ?string
    {
        return $this->upload ? $this->upload->depo : null;
    }

    public function getDistributorCustomerCodeAttribute(): ?string
    {
        return $this->upload ? $this->upload->distributor_customer_code : null;
    }

    public function getDistributorCustomerNameAttribute(): ?string
    {
        return $this->upload ? $this->upload->distributor_customer_name : null;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->upload ? $this->upload->address : null;
    }

    public function getCityAttribute(): ?string
    {
        return $this->upload ? $this->upload->city : null;
    }

    public function getDistrictAttribute(): ?string
    {
        return $this->upload ? $this->upload->district : null;
    }

    public function getSalesmanNameAttribute(): ?string
    {
        return $this->upload ? $this->upload->salesman_name : null;
    }

    public function getItemCategoryAttribute(): ?string
    {
        return $this->upload ? $this->upload->item_category : null;
    }

    public function getTransactionTypeAttribute(): ?string
    {
        return $this->upload ? $this->upload->transaction_type : null;
    }

    public function getChannelAttribute(): ?string
    {
        return $this->upload ? $this->upload->channel : null;
    }

    public function getInvoiceNoAttribute(): ?string
    {
        return $this->upload ? $this->upload->invoice_no : null;
    }

    public function getDppAttribute(): ?float
    {
        return $this->upload && $this->upload->dpp !== null ? (float)$this->upload->dpp : null;
    }

    public function getDiskon1Attribute(): ?float
    {
        return $this->upload && $this->upload->diskon_1 !== null ? (float)$this->upload->diskon_1 : null;
    }

    public function getDiskon2Attribute(): ?float
    {
        return $this->upload && $this->upload->diskon_2 !== null ? (float)$this->upload->diskon_2 : null;
    }

    public function getDiskon3Attribute(): ?float
    {
        return $this->upload && $this->upload->diskon_3 !== null ? (float)$this->upload->diskon_3 : null;
    }

    public function getDiskon4Attribute(): ?float
    {
        return $this->upload && $this->upload->diskon_4 !== null ? (float)$this->upload->diskon_4 : null;
    }

    public function getDiskon5Attribute(): ?float
    {
        return $this->upload && $this->upload->diskon_5 !== null ? (float)$this->upload->diskon_5 : null;
    }

    public function getPpnAttribute(): ?float
    {
        return $this->upload && $this->upload->ppn !== null ? (float)$this->upload->ppn : null;
    }

    public function getNetAttribute(): ?float
    {
        return $this->upload && $this->upload->net !== null ? (float)$this->upload->net : null;
    }

    /**
     * Get the raw upload record associated with this calculation.
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(TrxProgramUpload::class, 'upload_id');
    }

    /**
     * Get the program applied to this calculation.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(MstProgram::class, 'program_id');
    }
}
