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
            [
                'id' => 'charts-maps',
                'title' => 'Charts-maps',
                'type' => 'group',
                'children' => [
                    [
                        'id' => 'charts',
                        'title' => 'Charts',
                        'type' => 'collapse',
                        'icon' => 'ph ph-chart-donut',
                        'selected' => true,
                        'children' => [
                            [
                                'id' => 'apex-chart',
                                'title' => 'Apex chart',
                                'type' => 'item',
                                'url' => '/charts/apex-chart',
                            ],
                        ],
                    ],
                    [
                        'id' => 'map',
                        'title' => 'Map',
                        'type' => 'collapse',
                        'icon' => 'ph ph-map-trifold',
                        'children' => [
                            [
                                'id' => 'google-map',
                                'title' => 'Google map',
                                'type' => 'item',
                                'url' => '/map/google-map',
                            ],
                        ],
                    ],
                ],
            ],
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
