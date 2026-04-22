<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['name' => 'administrator'],
            ['name' => 'admin pusat'],
            ['name' => 'admin cabang'],
            ['name' => 'customer service (cs)'],
            ['name' => 'operator alat'],
            ['name' => 'sales'],
        ]);
    }
}
