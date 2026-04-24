<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleMenuPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('role_menu_permissions')->truncate();

        $permissionGroups = [
            1 => [
                'menus' => array_merge(range(1, 20), [31, 32, 34, 35, 36]),
                'flags' => [
                    'can_create' => true,
                    'can_read' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ],
            2 => [
                'menus' => [1, 3, 4, 5, 6, 7, 9, 10, 12, 20, 13, 15, 16, 17, 31, 32, 34, 35, 36],
                'flags' => [
                    'can_create' => true,
                    'can_read' => true,
                    'can_update' => true,
                    'can_delete' => true,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ],
            3 => [
                'menus' => [1, 5, 6, 7, 12, 20, 13, 34, 35, 36],
                'flags' => [
                    'can_create' => true,
                    'can_read' => true,
                    'can_update' => false,
                    'can_delete' => false,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ],
            4 => [
                'menus' => [5, 12, 20, 13, 34, 35, 36],
                'flags' => [
                    'can_create' => true,
                    'can_read' => true,
                    'can_update' => false,
                    'can_delete' => false,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ],
            5 => [
                'menus' => [7, 12, 20, 35, 36],
                'flags' => [
                    'can_create' => false,
                    'can_read' => true,
                    'can_update' => false,
                    'can_delete' => false,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ],
            6 => [
                'menus' => [5, 12, 20, 34, 35, 36],
                'flags' => [
                    'can_create' => true,
                    'can_read' => true,
                    'can_update' => false,
                    'can_delete' => false,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ],
        ];

        $reportReadOnlyMenus = [21, 22, 23, 24, 25, 26, 28, 29, 30];

        foreach ([2, 3] as $roleId) {
            $permissionGroups[$roleId . '-reports'] = [
                'role_id' => $roleId,
                'menus' => $reportReadOnlyMenus,
                'flags' => [
                    'can_create' => false,
                    'can_read' => true,
                    'can_update' => false,
                    'can_delete' => false,
                    'can_detail' => true,
                    'can_print' => false,
                    'can_export' => false,
                ],
            ];
        }

        $rows = [];

        foreach ($permissionGroups as $roleId => $config) {
            $resolvedRoleId = is_array($config) && array_key_exists('role_id', $config)
                ? $config['role_id']
                : (int) $roleId;

            foreach ($config['menus'] as $menuId) {
                $rows[] = array_merge([
                    'role_id' => $resolvedRoleId,
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $config['flags']);
            }
        }

        DB::table('role_menu_permissions')->insert($rows);
    }
}

