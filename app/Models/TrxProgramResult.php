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
    ];

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
