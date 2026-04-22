<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            $this->call([
                RoleSeeder::class,
                BranchSeeder::class,
                UserSeeder::class,
                BranchBankAccountSeeder::class,
                BranchBankSettingSeeder::class,
                MachineTypeSeeder::class,
                ComponentCategorySeeder::class,
                ComponentSampleSeeder::class,
                SizeSeeder::class,
                PlateTypeSeeder::class,
                MenuSeeder::class,
                RoleMenuSeeder::class,
                RoleMenuPermissionSeeder::class,
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
