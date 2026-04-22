<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('id', 'name');
        $menus = DB::table('menus')->pluck('id', 'key');

        // helper function
        $attach = function ($role, $menuKeys) use ($roles, $menus) {
            foreach ($menuKeys as $key) {
                if (isset($menus[$key])) {
                    DB::table('role_menus')->insert([
                        'role_id' => $roles[$role],
                        'menu_id' => $menus[$key],
                    ]);
                }
            }
        };

        // Example usage (replace with actual role-menu associations)
        // $attach('admin', ['dashboard', 'master-data', 'product', 'transaction', 'setting']);
        // $attach('user', ['dashboard', 'product']);
        // $attach('sales', ['dashboard', 'transaction']);

        // =========================
        // ADMINISTRATOR (ALL)
        // =========================
        foreach ($roles as $roleName => $roleId) {
            if ($roleName === 'administrator') {
                foreach ($menus as $menuId) {
                    DB::table('role_menus')->insert([
                        'role_id' => $roleId,
                        'menu_id' => $menuId,
                    ]);
                }
            }
        }

        // =========================
        // ADMIN PUSAT
        // =========================
        $attach('admin pusat', [
            'dashboard',
            'master-data',
            'branch',
            'user',
            'customer',
            'sales',
            'machine',
            'product',
            'plate',
            'cutting-price',
            'transaction',
            'invoice',
            'production',
            'payment',
            'setting',
            'plate-material',
            'machine-type',
            'plate-size',
            'role',
            'permission',
            'report',
            'report-customer-ranking',
            'report-sales-kpi',
            'report-invoice-recap',
            'report-payment-recap',
            'report-plate-sales-recap',
            'report-cutting-sales-recap',
            'report-receivable',
            'report-bank-reconcile',
            'report-stock',
        ]);

        // =========================
        // ADMIN CABANG
        // =========================
        $attach('admin cabang', [
            'dashboard',
            'master-data',
            'user',
            'customer',
            'sales',
            'machine',
            'product',
            'plate',
            'cutting-price',
            'transaction',
            'invoice',
            'production',
            'payment',
            'report',
            'report-customer-ranking',
            'report-sales-kpi',
            'report-invoice-recap',
            'report-payment-recap',
            'report-plate-sales-recap',
            'report-cutting-sales-recap',
            'report-receivable',
            'report-bank-reconcile',
            'report-stock',
        ]);

        // =========================
        // customer service (cs)
        // =========================
        $attach('customer service (cs)', [
            'dashboard',
            'customer',
            'invoice',
            'production',
            'payment',
        ]);

        // =========================
        // OPERATOR ALAT
        // =========================
        $attach('operator alat', [
            'dashboard',
            'machine',
            'invoice',
            'production',
        ]);

        // =========================
        // SALES
        // =========================
        $attach('sales', [
            'dashboard',
            'customer',
            'invoice',
            'production',
        ]);
    }
}
