<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = \App\Models\Role::where('name', 'administrator')->first();

        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'role_id' => $adminRole ? $adminRole->id : null,
                'name' => 'Administrator PT Susanti Megah',
                'email' => 'admin@susantimegah.com',
                'password' => Hash::make('password'),
                'code_customer' => 'C110000411',
                'is_active' => true,
            ]
        );
    }
}
