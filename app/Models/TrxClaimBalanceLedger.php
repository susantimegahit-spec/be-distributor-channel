<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrxClaimBalanceLedger extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trx_claim_balance_ledger';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_code',
        'ref_number',
        'transaction_date',
        'type',
        'debit',
        'credit',
        'running_balance',
        'claim_type',
        'claim_start',
        'claim_end',
        'description',
        'referenceable_id',
        'referenceable_type',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
        'claim_start' => 'date:Y-m-d',
        'claim_end' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'customer_name',
        'depo',
    ];

    /**
     * Get the parent referenceable model.
     */
    public function referenceable()
    {
        return $this->morphTo();
    }

    /**
     * Get the distributor associated with the ledger entry.
     */
    public function distributor()
    {
        return $this->belongsTo(Distributor::class, 'customer_code', 'code_customer');
    }

    /**
     * Accessor for customer_name.
     */
    public function getCustomerNameAttribute(): ?string
    {
        return $this->distributor?->name;
    }

    /**
     * Accessor for depo.
     */
    public function getDepoAttribute(): ?string
    {
        return $this->distributor?->depo;
    }
}
