<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlateTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('plate_types')->insert([
            ['name' => 'Aluminium'],
            ['name' => 'Stainless'],
            ['name' => 'Besi'],
            ['name' => 'Kuningan'],
            ['name' => 'Galvanis'],
        ]);
    }
}
