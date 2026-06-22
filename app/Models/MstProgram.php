<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MstProgram extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mst_program';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'program_code',
        'program_name',
        'start_date',
        'end_date',
        'description',
        'status',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'item_names',
    ];

    /**
     * Get the item names as a comma-separated string.
     */
    public function getItemNamesAttribute(): string
    {
        return $this->items->pluck('item_name')->implode(', ');
    }

    /**
     * Get the strata associated with the program.
     */
    public function strata(): HasMany
    {
        return $this->hasMany(MstProgramStrata::class, 'program_id');
    }

    /**
     * Get the items associated with the program.
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'mst_program_item', 'program_id', 'item_id');
    }
}
