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
            ['code_customer' => 'C110003419'],
            [
                'name' => 'SAKTI SETIA SANTOSA, PT',
                'address' => 'Jl. Sakti Setia No. 456',
                'phone' => '021-87654321',
                'email' => 'saktisetia@example.com',
                'mail_address' => 'Jl. Sakti Setia No. 456, Kantor Pos',
                'contact_person' => 'Jane Smith',
                'sub_group' => 'Distributor',
                'depo' => 'SURABAYA',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
