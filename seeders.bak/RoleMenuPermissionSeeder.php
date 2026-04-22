<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleMenuPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('role_menu_permissions')->truncate();

        $roles = DB::table('roles')->pluck('id', 'name');
        $menus = DB::table('menus')->pluck('id', 'key');

        $insert = function ($roleName, $menuKeys, $permission) use ($roles, $menus) {
            foreach ($menuKeys as $key) {
                if (!isset($menus[$key])) continue;

                DB::table('role_menu_permissions')->insert([
                    'role_id' => $roles[$roleName],
                    'menu_id' => $menus[$key],

                    'can_create' => $permission['create'] ?? false,
                    'can_read'   => $permission['read'] ?? false,
                    'can_update' => $permission['update'] ?? false,
                    'can_delete' => $permission['delete'] ?? false,
                    'can_detail' => $permission['detail'] ?? false,
                ]);
            }
        };

        // =========================
        // ADMIN (FULL ACCESS)
        // =========================
        foreach ($roles as $roleName => $roleId) {
            if ($roleName === 'administrator') {
                foreach ($menus as $menuId) {
                    DB::table('role_menu_permissions')->insert([
                        'role_id' => $roleId,
                        'menu_id' => $menuId,
                        'can_create' => true,
                        'can_read' => true,
                        'can_update' => true,
                        'can_delete' => true,
                        'can_detail' => true,
                    ]);
                }
            }
        }

        // =========================
        // ADMIN PUSAT
        // =========================
        $insert('admin pusat', [
            'dashboard',
            'branch',
            'user',
            'customer',
            'sales',
            'machine',
            'plate',
            'cutting-price',
            'invoice',
            'production',
            'payment',
            'plate-material',
            'machine-type',
            'plate-size',
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
        ], [
            'create' => true,
            'read' => true,
            'update' => true,
            'delete' => true,
            'detail' => true,
        ]);

        // =========================
        // ADMIN CABANG
        // =========================
        $insert('admin cabang', [
            'dashboard',
            'customer',
            'sales',
            'machine',
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
        ], [
            'create' => true,
            'read' => true,
            'update' => false,
            'delete' => false,
            'detail' => true,
        ]);

        // =========================
        // CS
        // =========================
        $insert('customer service (cs)', [
            'customer',
            'invoice',
            'production',
            'payment',
        ], [
            'create' => true,
            'read' => true,
            'update' => false,
            'delete' => false,
            'detail' => true,
        ]);

        // =========================
        // OPERATOR
        // =========================
        $insert('operator alat', [
            'machine',
            'invoice',
            'production',
        ], [
            'create' => false,
            'read' => true,
            'update' => false,
            'delete' => false,
            'detail' => true,
        ]);

        // =========================
        // SALES
        // =========================
        $insert('sales', [
            'customer',
            'invoice',
            'production',
        ], [
            'create' => true,
            'read' => true,
            'update' => false,
            'delete' => false,
            'detail' => true,
        ]);
    }
}
