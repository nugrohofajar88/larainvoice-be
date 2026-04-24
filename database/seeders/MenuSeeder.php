<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('role_menu_permissions')->truncate();
        DB::table('role_menus')->truncate();
        DB::table('menus')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        DB::table('menus')->insert([
            [
                'id' => 1,
                'name' => 'Dashboard',
                'key' => 'dashboard',
                'parent_id' => null,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Master Data',
                'key' => 'master-data',
                'parent_id' => null,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'name' => 'Kantor Cabang',
                'key' => 'branch',
                'parent_id' => 2,
                'sort_order' => 1,
            ],
            [
                'id' => 4,
                'name' => 'User',
                'key' => 'user',
                'parent_id' => 2,
                'sort_order' => 2,
            ],
            [
                'id' => 5,
                'name' => 'Pelanggan',
                'key' => 'customer',
                'parent_id' => 2,
                'sort_order' => 3,
            ],
            [
                'id' => 6,
                'name' => 'Sales',
                'key' => 'sales',
                'parent_id' => 2,
                'sort_order' => 4,
            ],
            [
                'id' => 7,
                'name' => 'Mesin',
                'key' => 'machine',
                'parent_id' => 2,
                'sort_order' => 5,
            ],
            [
                'id' => 31,
                'name' => 'Kategori',
                'key' => 'component-category',
                'parent_id' => 2,
                'sort_order' => 6,
            ],
            [
                'id' => 34,
                'name' => 'Tipe Biaya',
                'key' => 'cost-type',
                'parent_id' => 2,
                'sort_order' => 7,
            ],
            [
                'id' => 8,
                'name' => 'Product',
                'key' => 'product',
                'parent_id' => null,
                'sort_order' => 3,
            ],
            [
                'id' => 9,
                'name' => 'Plat',
                'key' => 'plate',
                'parent_id' => 8,
                'sort_order' => 1,
            ],
            [
                'id' => 10,
                'name' => 'Jasa Cutting',
                'key' => 'cutting-price',
                'parent_id' => 8,
                'sort_order' => 2,
            ],
            [
                'id' => 32,
                'name' => 'Komponen',
                'key' => 'component',
                'parent_id' => 8,
                'sort_order' => 3,
            ],
            [
                'id' => 11,
                'name' => 'Transaksi',
                'key' => 'transaction',
                'parent_id' => null,
                'sort_order' => 4,
            ],
            [
                'id' => 12,
                'name' => 'Penjualan (Invoice)',
                'key' => 'invoice',
                'parent_id' => 11,
                'sort_order' => 1,
            ],
            [
                'id' => 20,
                'name' => 'List Penjualan',
                'key' => 'production',
                'parent_id' => 11,
                'sort_order' => 2,
            ],
            [
                'id' => 13,
                'name' => 'Pembayaran',
                'key' => 'payment',
                'parent_id' => 11,
                'sort_order' => 3,
            ],
            [
                'id' => 35,
                'name' => 'Order Mesin',
                'key' => 'machine-order',
                'parent_id' => 11,
                'sort_order' => 4,
            ],
            [
                'id' => 36,
                'name' => 'Order Jasa',
                'key' => 'service-order',
                'parent_id' => 11,
                'sort_order' => 5,
            ],
            [
                'id' => 14,
                'name' => 'Pengaturan',
                'key' => 'setting',
                'parent_id' => null,
                'sort_order' => 5,
            ],
            [
                'id' => 15,
                'name' => 'Bahan Plat',
                'key' => 'plate-material',
                'parent_id' => 14,
                'sort_order' => 1,
            ],
            [
                'id' => 16,
                'name' => 'Tipe Mesin',
                'key' => 'machine-type',
                'parent_id' => 14,
                'sort_order' => 2,
            ],
            [
                'id' => 17,
                'name' => 'Plat Size',
                'key' => 'plate-size',
                'parent_id' => 14,
                'sort_order' => 3,
            ],
            [
                'id' => 18,
                'name' => 'Role',
                'key' => 'role',
                'parent_id' => 14,
                'sort_order' => 4,
            ],
            [
                'id' => 19,
                'name' => 'Menu',
                'key' => 'menu-setting',
                'parent_id' => 14,
                'sort_order' => 5,
            ],
            [
                'id' => 21,
                'name' => 'Laporan',
                'key' => 'report',
                'parent_id' => null,
                'sort_order' => 6,
            ],
            [
                'id' => 22,
                'name' => 'Ranking Pelanggan',
                'key' => 'report-customer-ranking',
                'parent_id' => 21,
                'sort_order' => 1,
            ],
            [
                'id' => 23,
                'name' => 'KPI Sales',
                'key' => 'report-sales-kpi',
                'parent_id' => 21,
                'sort_order' => 2,
            ],
            [
                'id' => 24,
                'name' => 'Rekap Invoice',
                'key' => 'report-invoice-recap',
                'parent_id' => 21,
                'sort_order' => 3,
            ],
            [
                'id' => 25,
                'name' => 'Rekap Pembayaran',
                'key' => 'report-payment-recap',
                'parent_id' => 21,
                'sort_order' => 4,
            ],
            [
                'id' => 29,
                'name' => 'Rekap Penjualan Plat',
                'key' => 'report-plate-sales-recap',
                'parent_id' => 21,
                'sort_order' => 5,
            ],
            [
                'id' => 30,
                'name' => 'Rekap Penjualan Jasa Cutting',
                'key' => 'report-cutting-sales-recap',
                'parent_id' => 21,
                'sort_order' => 6,
            ],
            [
                'id' => 26,
                'name' => 'Piutang',
                'key' => 'report-receivable',
                'parent_id' => 21,
                'sort_order' => 7,
            ],
            [
                'id' => 28,
                'name' => 'Stok',
                'key' => 'report-stock',
                'parent_id' => 21,
                'sort_order' => 9,
            ],
        ]);
    }
}

