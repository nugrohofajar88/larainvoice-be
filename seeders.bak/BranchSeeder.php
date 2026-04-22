<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        DB::table('branches')->insert([
            [
                'name' => 'Kantor Pusat Malang',
                'city' => 'Malang',
                'address' => 'Jl. Raya Sekarpuro No. 32,  Kec. Pakis Kab. Malang 65154',
                'phone' => '081216062649',
                'email' => 'pioneerjasacnc@gmail.com',
                'website' => 'pioneercncindonesia.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kantor Cabang Mojokerto',
                'city' => 'Mojokerto',
                'address' => 'Jl. Gempol-Mojokerto, Sebelah timur SPBU jasem',
                'phone' => '085731902449',
                'email' => 'mojokerto@pioneer.com',
                'website' => 'pioneercncindonesia.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
