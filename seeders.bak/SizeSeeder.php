<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sizes')->insert([
            ['value' => 1.00],
            ['value' => 1.20],
            ['value' => 1.40],
            ['value' => 1.50],
            ['value' => 1.60],
            ['value' => 1.80],
            ['value' => 2.00],
            ['value' => 2.20],
            ['value' => 2.30],
            ['value' => 2.50],
            ['value' => 2.70],
            ['value' => 3.00],
            ['value' => 3.20],
            ['value' => 3.50],
            ['value' => 3.80],
            ['value' => 4.00],
            ['value' => 4.40],
            ['value' => 4.70],
            ['value' => 5.00],
            ['value' => 5.50],
            ['value' => 6.00],
            ['value' => 8.00],
            ['value' => 10.00],
            ['value' => 14.00],
            ['value' => 12.00],
            ['value' => 7.00],
        ]);
    }
}
