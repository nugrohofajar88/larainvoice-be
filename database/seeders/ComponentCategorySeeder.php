<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComponentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('component_categories')->upsert([
            [
                'id' => 1,
                'name' => 'Konsumable',
                'created_at' => '2026-04-18 17:21:13',
                'updated_at' => '2026-04-18 17:21:13',
            ],
            [
                'id' => 2,
                'name' => 'Mur Baut',
                'created_at' => '2026-04-18 17:21:22',
                'updated_at' => '2026-04-18 17:21:22',
            ],
            [
                'id' => 3,
                'name' => 'Motor dan Driver',
                'created_at' => '2026-04-18 17:21:33',
                'updated_at' => '2026-04-18 17:21:33',
            ],
            [
                'id' => 4,
                'name' => 'Mekanikal',
                'created_at' => '2026-04-18 17:21:43',
                'updated_at' => '2026-04-18 17:21:43',
            ],
            [
                'id' => 5,
                'name' => 'Elektrik',
                'created_at' => '2026-04-18 17:21:59',
                'updated_at' => '2026-04-18 17:21:59',
            ],
            [
                'id' => 6,
                'name' => 'Finishing',
                'created_at' => '2026-04-18 17:22:09',
                'updated_at' => '2026-04-18 17:22:09',
            ],
            [
                'id' => 7,
                'name' => 'Alat Habis Pakai',
                'created_at' => '2026-04-18 17:22:20',
                'updated_at' => '2026-04-18 17:22:20',
            ],
        ], ['id'], ['name', 'created_at', 'updated_at']);
    }
}
