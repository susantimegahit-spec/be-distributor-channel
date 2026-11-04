<?php

namespace Database\Seeders;

use App\Models\PiSetting;
use Illuminate\Database\Seeder;

class PiSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PiSetting::updateOrCreate(
            ['id' => 1],
            [
                'signer_name' => 'Kushan Wijono',
                'signer_title' => 'Branch Manager',
                'signature_path' => null,
            ]
        );
    }
}
