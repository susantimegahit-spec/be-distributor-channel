<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleMenu;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define roles
        $roles = [
            'distributor',
            'admin sales',
            'admin finance',
            'admin logistic',
            'administrator',
        ];

        $roleModels = [];

        foreach ($roles as $roleName) {
            $roleModels[$roleName] = Role::updateOrCreate(
                ['name' => $roleName],
                ['is_active' => true]
            );
        }

        // Seed default menu for 'administrator'
        $adminMenuJson = [
            "master-distributor",
            "master-product",
            "master-buying-price",
            "master-selling-price",
            "order-list",
            "order-retur"
        ];

        $adminRole = $roleModels['administrator'];

        RoleMenu::updateOrCreate(
            ['role_id' => $adminRole->id],
            [
                'menu' => $adminMenuJson,
                'is_active' => true,
            ]
        );
    }
}
