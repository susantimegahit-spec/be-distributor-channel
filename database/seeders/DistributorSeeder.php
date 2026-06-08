<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistributorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('distributors')->updateOrInsert(
            ['code_customer' => 'C110000411'],
            [
                'name' => 'PT XYZ',
                'address' => 'Jl. Dummy No. 123',
                'phone' => '021-12345678',
                'email' => 'info@xyz.com',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
