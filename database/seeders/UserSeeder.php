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
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator PT Susanti Megah',
                'email' => 'admin@susantimegah.com',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }
}
