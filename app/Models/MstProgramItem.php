<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MstProgramItem extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mst_program_item';

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
        'item_id',
    ];
}
