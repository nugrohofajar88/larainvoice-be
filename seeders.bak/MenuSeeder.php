<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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

        // =========================
        // 1. DASHBOARD
        // =========================
        DB::table('menus')->insert([
            [
                'id' => 1,
                'name' => 'Dashboard',
                'key' => 'dashboard',
                'parent_id' => null,
                'sort_order' => 1,
            ],
        ]);

        // =========================
        // 2. MASTER DATA (PARENT)
        // =========================
        $masterId = DB::table('menus')->insertGetId([
            'name' => 'Master Data',
            'key' => 'master-data',
            'parent_id' => null,
            'sort_order' => 2,
        ]);

        DB::table('menus')->insert([
            [
                'name' => 'Kantor Cabang',
                'key' => 'branch',
                'parent_id' => $masterId,
                'sort_order' => 1,
            ],
            [
                'name' => 'User',
                'key' => 'user',
                'parent_id' => $masterId,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pelanggan',
                'key' => 'customer',
                'parent_id' => $masterId,
                'sort_order' => 3,
            ],
            [
                'name' => 'Sales',
                'key' => 'sales',
                'parent_id' => $masterId,
                'sort_order' => 4,
            ],
            [
                'name' => 'Mesin',
                'key' => 'machine',
                'parent_id' => $masterId,
                'sort_order' => 5,
            ],
        ]);

        // =========================
        // 3. PRODUCT
        // =========================
        $productId = DB::table('menus')->insertGetId([
            'name' => 'Product',
            'key' => 'product',
            'parent_id' => null,
            'sort_order' => 3,
        ]);

        DB::table('menus')->insert([
            [
                'name' => 'Plat',
                'key' => 'plate',
                'parent_id' => $productId,
                'sort_order' => 1,
            ],
            [
                'name' => 'Jasa',
                'key' => 'cutting-price',
                'parent_id' => $productId,
                'sort_order' => 2,
            ],
        ]);

        // =========================
        // 4. TRANSAKSI
        // =========================
        $trxId = DB::table('menus')->insertGetId([
            'name' => 'Transaksi',
            'key' => 'transaction',
            'parent_id' => null,
            'sort_order' => 4,
        ]);

        DB::table('menus')->insert([
            [
                'name' => 'Penjualan (Invoice)',
                'key' => 'invoice',
                'parent_id' => $trxId,
                'sort_order' => 1,
            ],
            [
                'name' => 'List Penjualan',
                'key' => 'production',
                'parent_id' => $trxId,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pembayaran',
                'key' => 'payment',
                'parent_id' => $trxId,
                'sort_order' => 3,
            ],
        ]);

        // =========================
        // 5. PENGATURAN
        // =========================
        $settingId = DB::table('menus')->insertGetId([
            'name' => 'Pengaturan',
            'key' => 'setting',
            'parent_id' => null,
            'sort_order' => 5,
        ]);

        DB::table('menus')->insert([
            [
                'name' => 'Bahan Plat',
                'key' => 'plate-material',
                'parent_id' => $settingId,
                'sort_order' => 1,
            ],
            [
                'name' => 'Tipe Mesin',
                'key' => 'machine-type',
                'parent_id' => $settingId,
                'sort_order' => 2,
            ],
            [
                'name' => 'Plat Size',
                'key' => 'plate-size',
                'parent_id' => $settingId,
                'sort_order' => 3,
            ],
            [
                'name' => 'Role',
                'key' => 'role',
                'parent_id' => $settingId,
                'sort_order' => 4,
            ],
            [
                'name' => 'Menu',
                'key' => 'menu-setting',
                'parent_id' => $settingId,
                'sort_order' => 5,
            ],
        ]);

        // =========================
        // 6. LAPORAN
        // =========================
        $reportId = DB::table('menus')->insertGetId([
            'name' => 'Laporan',
            'key' => 'report',
            'parent_id' => null,
            'sort_order' => 6,
        ]);

        DB::table('menus')->insert([
            [
                'name' => 'Ranking Pelanggan',
                'key' => 'report-customer-ranking',
                'parent_id' => $reportId,
                'sort_order' => 1,
            ],
            [
                'name' => 'KPI Sales',
                'key' => 'report-sales-kpi',
                'parent_id' => $reportId,
                'sort_order' => 2,
            ],
            [
                'name' => 'Rekap Invoice',
                'key' => 'report-invoice-recap',
                'parent_id' => $reportId,
                'sort_order' => 3,
            ],
            [
                'name' => 'Rekap Pembayaran',
                'key' => 'report-payment-recap',
                'parent_id' => $reportId,
                'sort_order' => 4,
            ],
            [
                'name' => 'Rekap Penjualan Plat',
                'key' => 'report-plate-sales-recap',
                'parent_id' => $reportId,
                'sort_order' => 5,
            ],
            [
                'name' => 'Rekap Penjualan Jasa Cutting',
                'key' => 'report-cutting-sales-recap',
                'parent_id' => $reportId,
                'sort_order' => 6,
            ],
            [
                'name' => 'Piutang',
                'key' => 'report-receivable',
                'parent_id' => $reportId,
                'sort_order' => 7,
            ],
            [
                'name' => 'Rekonsiliasi Bank',
                'key' => 'report-bank-reconcile',
                'parent_id' => $reportId,
                'sort_order' => 8,
            ],
            [
                'name' => 'Stok',
                'key' => 'report-stock',
                'parent_id' => $reportId,
                'sort_order' => 9,
            ],
        ]);
    }
}
