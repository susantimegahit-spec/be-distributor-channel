<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterApprovalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            [
                'id' => 1,
                'name' => 'DRAFT',
                'action' => 'create draft',
                'notification_type' => 'none',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'WAITING_OM',
                'action' => 'approve/reject',
                'notification_type' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'WAITING_ASM',
                'action' => 'approve/reject',
                'notification_type' => 'email',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'WAITING_ADMIN_SALES',
                'action' => 'isi diskon & kirim approval',
                'notification_type' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'WAITING_FINANCE',
                'action' => 'approve/reject',
                'notification_type' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'name' => 'COMPLETED',
                'action' => 'none',
                'notification_type' => 'none',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('master_approvals')->insert($stages);
    }
}
