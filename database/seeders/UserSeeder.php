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
        $salesRole = \App\Models\Role::where('name', 'admin sales')->first();
        $distributorRole = \App\Models\Role::where('name', 'distributor')->first();

        // 1. User Administrator (without distributor code)
        User::updateOrCreate(
            ['username' => 'administrator'],
            [
                'role_id' => $adminRole ? $adminRole->id : null,
                'name' => 'Administrator PT Susanti Megah',
                'email' => 'administrator@susantimegah.com',
                'password' => Hash::make('password'),
                'code_customer' => null,
                'is_active' => true,
            ]
        );

        // 2. User Admin Sales (without distributor code)
        User::updateOrCreate(
            ['username' => 'adminsales'],
            [
                'role_id' => $salesRole ? $salesRole->id : null,
                'name' => 'Admin Sales PT Susanti Megah',
                'email' => 'adminsales@susantimegah.com',
                'password' => Hash::make('password'),
                'code_customer' => null,
                'is_active' => true,
            ]
        );

        // 3. User Distributor A (with code C110003419)
        User::updateOrCreate(
            ['username' => 'dist123'],
            [
                'role_id' => $distributorRole ? $distributorRole->id : null,
                'name' => 'SAKTI SETIA SANTOSA, PT',
                'email' => 'saktisetia@example.com',
                'password' => Hash::make('password'),
                'code_customer' => 'C110003419',
                'is_active' => true,
            ]
        );
    }
}
