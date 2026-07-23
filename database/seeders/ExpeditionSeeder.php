<?php

namespace Database\Seeders;

use App\Models\Expedition;
use Illuminate\Database\Seeder;

class ExpeditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $expeditions = [
            'Anugerah Express',
            'Lima Cahaya',
            'Sari Abadi',
            'Duta',
            'Fastana',
            'Hacaca',
            'Hartini',
            'Indo Sari Bumi',
            'Popeye',
            'Saudara Makmur',
            'Siba',
            'Tentrem',
            'Tepat Jaya',
            'Valindo',
            'KS Express',
            'Bintang Lombok',
            'Buana Transindo',
            'Fajar Mulia',
            'MAJR',
        ];

        foreach ($expeditions as $name) {
            // Check if already exists to avoid duplication
            $exists = Expedition::where('expedition_name', $name)->exists();
            if (!$exists) {
                Expedition::create([
                    'expedition_code' => Expedition::generateCode(),
                    'expedition_name' => $name,
                    'status'          => 'ACTIVE',
                ]);
            }
        }
    }
}
