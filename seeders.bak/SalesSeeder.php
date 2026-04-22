<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        // ambil role sales
        $role = DB::table('roles')->where('name', 'sales')->first();

        if (!$role) {
            return;
        }

        DB::table('users')->insert([
            [
                'name' => 'Sales Surabaya',
                'username' => 'sales_sby',
                'email' => 'sales1@example.com',
                'password' => bcrypt('password'),
                'role_id' => $role->id,
                'branch_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sales Malang',
                'username' => 'sales_mlg',
                'email' => 'sales2@example.com',
                'password' => bcrypt('password'),
                'role_id' => $role->id,
                'branch_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('sales_profiles')->insert([
            [
                'user_id' => 1,
                'nik' => '5432167890',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 2,
                'nik' => '0987654321',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

    }
}