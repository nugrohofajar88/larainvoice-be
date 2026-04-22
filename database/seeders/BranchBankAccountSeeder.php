<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchBankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('branch_bank_accounts')->truncate();

        DB::table('branch_bank_accounts')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'bank_name' => 'BCASyariah',
                'account_number' => '0530065390',
                'account_holder' => 'Fredy Nasution',
                'bank_code' => null,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
