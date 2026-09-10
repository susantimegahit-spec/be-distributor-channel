<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'code_customer',
        'name',
        'address',
        'phone',
        'email',
        'mail_address',
        'contact_person',
        'sub_group',
        'depo',
        'status',
        'bank_code',
        'bank_name',
        'client_bank_name',
        'account_bank_number',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * Get the users associated with the distributor.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'code_customer', 'code_customer');
    }
}
