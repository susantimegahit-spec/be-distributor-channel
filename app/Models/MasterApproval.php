<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterApproval extends Model
{
    use HasFactory;

    protected $table = 'master_approvals';

    protected $fillable = [
        'name',
        'label',
        'action',
        'notification_type',
    ];

    /**
     * Get the sales orders at this approval stage.
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'approval_id');
    }

    /**
     * Get the role menus linked to this approval stage.
     */
    public function roleMenus(): HasMany
    {
        return $this->hasMany(RoleMenu::class, 'approval_id');
    }
}
