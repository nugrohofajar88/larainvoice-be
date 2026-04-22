<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            BranchSeeder::class,
            BranchBulkSeeder::class,
            SalesSeeder::class,
            CustomerSeeder::class,

            BranchBankAccountSeeder::class,
            BranchBankSettingSeeder::class,

            MachineTypeSeeder::class,
            SizeSeeder::class,
            PlateTypeSeeder::class,

            MenuSeeder::class,
            RoleMenuSeeder::class,
            RoleMenuPermissionSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Developer',
            'username' => 'developer',
            'password' => 'developer123',
            'email' => 'developer@example.com',
            'role_id' => 1, // Assuming 'administrator' role has ID 1
            'branch_id' => 1, // Assuming 'Pusat' branch has ID 1
        ]);
        User::factory()->create([
            'name' => 'Administrator',
            'username' => 'administrator',
            'password' => 'lancarjaya123',
            'email' => 'administrator@example.com',
            'role_id' => 2, // Assuming 'Admin Pusat' role has ID 2
            'branch_id' => 1, // Assuming 'Pusat' branch has ID 1
        ]);
        User::factory()->create([
            'name' => 'Admin Cabang',
            'username' => 'cabang',
            'password' => 'cabang123',
            'email' => 'cabang@example.com',
            'role_id' => 3, // Assuming 'Admin Cabang' role has ID 3
            'branch_id' => 1, // Assuming 'Pusat' branch has ID 1
        ]);
        User::factory()->create([
            'name' => 'Customer Support (CS)',
            'username' => 'susi',
            'password' => 'susi123',
            'email' => 'susi@example.com',
            'role_id' => 4, // Assuming 'Customer Support' role has ID 4
            'branch_id' => 1, // Assuming 'Pusat' branch has ID 1
        ]);
        
        
    }
}
