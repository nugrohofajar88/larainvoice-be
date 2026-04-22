<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('role_menus')->truncate();

        $roleMenus = [
            1 => array_merge(array_diff(range(1, 30), [27]), [31, 32, 34, 35]),
            2 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 20, 21, 22, 23, 24, 25, 26, 28, 29, 30, 31, 32, 34, 35],
            3 => [1, 2, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 20, 21, 22, 23, 24, 25, 26, 28, 29, 30, 34, 35],
            4 => [1, 5, 12, 13, 20, 34, 35],
            5 => [1, 7, 12, 20, 35],
            6 => [1, 5, 12, 20, 34, 35],
        ];

        $rows = [];
        foreach ($roleMenus as $roleId => $menuIds) {
            foreach ($menuIds as $menuId) {
                $rows[] = [
                    'role_id' => $roleId,
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('role_menus')->insert($rows);
    }
}


