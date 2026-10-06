<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmSpaceMember extends CorporateModel
{
    protected $table = 'tm_space_members';

    const UPDATED_AT = null;

    protected $fillable = [
        'space_id',
        'employee_id',
        'space_role',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(TmSpace::class, 'space_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrisEmployee::class, 'employee_id');
    }
}
