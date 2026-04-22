<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchBankSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('branch_settings')->truncate();

        DB::table('branch_settings')->insert([
            [
                'id' => 1,
                'branch_id' => 1,
                'minimum_stock' => 2,
                'sales_commission_percentage' => 2.00,
                'invoice_header_name' => 'Shinta Putri',
                'invoice_header_position' => 'Admin Keuangan',
                'invoice_footer_note' => 'Terima kasih atas kepercayaan Anda. Kami berharap dapat terus melayani kebutuhan CNC Anda dengan baik.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
