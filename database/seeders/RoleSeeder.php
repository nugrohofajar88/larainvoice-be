<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->truncate();

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'administrator'],
            ['id' => 2, 'name' => 'admin pusat'],
            ['id' => 3, 'name' => 'admin cabang'],
            ['id' => 4, 'name' => 'customer service (cs)'],
            ['id' => 5, 'name' => 'operator alat'],
            ['id' => 6, 'name' => 'sales'],
        ]);
    }
}
