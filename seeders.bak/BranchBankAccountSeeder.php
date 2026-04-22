<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchBankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('branch_bank_accounts')->insert([
            [
                'branch_id' => 1,
                'bank_name' => 'BCASyariah',
                'account_number' => '0530065390',
                'account_holder' => 'Fredy Nasution ',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
