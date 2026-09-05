<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MstProgramStrata extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mst_program_strata';

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
        'program_id',
        'customer_type',
        'min_qty_kg',
        'max_qty_kg',
        'harga_program_per_kg',
        'diskon_per_kg',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_qty_kg' => 'decimal:2',
        'max_qty_kg' => 'decimal:2',
        'harga_program_per_kg' => 'decimal:2',
        'diskon_per_kg' => 'decimal:2',
    ];

    /**
     * Get the program that owns the strata.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(MstProgram::class, 'program_id');
    }
}
