<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\DiscountSetting;

class DiscountSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DiscountSetting::updateOrCreate(
            ['id' => 1],
            ['max_discount' => 20.00]
        );
    }
}
