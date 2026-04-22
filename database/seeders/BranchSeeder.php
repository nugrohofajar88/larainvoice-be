<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        DB::table('branches')->truncate();

        DB::table('branches')->insert([
            [
                'id' => 1,
                'name' => 'Kantor Malang',
                'city' => 'Kab.Malang',
                'address' => 'Jl. Raya Sekarpuro No. 32,  Kec. Pakis Kab. Malang 65154',
                'phone' => '081216062649',
                'email' => 'pioneerjasacnc@gmail.com',
                'website' => 'pioneercncindonesia.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
