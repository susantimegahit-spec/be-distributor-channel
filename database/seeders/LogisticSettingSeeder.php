<?php

namespace Database\Seeders;

use App\Models\LogisticSetting;
use Illuminate\Database\Seeder;

class LogisticSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LogisticSetting::updateOrCreate(
            ['key' => 'virtual_warehouse_code'],
            [
                'value' => 'VPGMN01',
                'description' => 'Default gudang virtual untuk mutasi stok Inventory Transfer (IT) dan Delivery Order (DO)',
            ]
        );
    }
}
