<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrxProgramUploadBatch extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trx_program_upload_batch';

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
        'batch_no',
        'file_name',
        'uploaded_by',
    ];

    /**
     * Get the upload records associated with this batch.
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(TrxProgramUpload::class, 'batch_id');
    }
}
