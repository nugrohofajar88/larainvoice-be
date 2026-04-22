<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportMenuSeeder extends Seeder
{
    public function run(): void
    {
        $reportId = DB::table('menus')->updateOrInsert(
            ['key' => 'report'],
            [
                'name' => 'Laporan',
                'parent_id' => null,
                'sort_order' => 6,
                'is_active' => true,
            ]
        );

        $reportMenuId = DB::table('menus')->where('key', 'report')->value('id');

        $children = [
            ['name' => 'Ranking Pelanggan', 'key' => 'report-customer-ranking', 'sort_order' => 1],
            ['name' => 'KPI Sales', 'key' => 'report-sales-kpi', 'sort_order' => 2],
            ['name' => 'Rekap Invoice', 'key' => 'report-invoice-recap', 'sort_order' => 3],
            ['name' => 'Rekap Pembayaran', 'key' => 'report-payment-recap', 'sort_order' => 4],
            ['name' => 'Rekap Penjualan Plat', 'key' => 'report-plate-sales-recap', 'sort_order' => 5],
            ['name' => 'Rekap Penjualan Jasa Cutting', 'key' => 'report-cutting-sales-recap', 'sort_order' => 6],
            ['name' => 'Piutang', 'key' => 'report-receivable', 'sort_order' => 7],
            ['name' => 'Rekonsiliasi Bank', 'key' => 'report-bank-reconcile', 'sort_order' => 8],
            ['name' => 'Stok', 'key' => 'report-stock', 'sort_order' => 9],
        ];

        foreach ($children as $child) {
            DB::table('menus')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_id' => $reportMenuId,
                    'sort_order' => $child['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $roles = DB::table('roles')->pluck('id', 'name');
        $menus = DB::table('menus')->pluck('id', 'key');
        $reportKeys = array_merge(['report'], array_column($children, 'key'));

        $attachMenus = function (string $roleName) use ($roles, $menus, $reportKeys) {
            if (!isset($roles[$roleName])) {
                return;
            }

            foreach ($reportKeys as $key) {
                if (!isset($menus[$key])) {
                    continue;
                }

                DB::table('role_menus')->updateOrInsert(
                    ['role_id' => $roles[$roleName], 'menu_id' => $menus[$key]],
                    []
                );
            }
        };

        foreach (['administrator', 'admin pusat', 'admin cabang'] as $roleName) {
            $attachMenus($roleName);
        }

        $setPermission = function (string $roleName, array $menuKeys) use ($roles, $menus) {
            if (!isset($roles[$roleName])) {
                return;
            }

            foreach ($menuKeys as $key) {
                if (!isset($menus[$key])) {
                    continue;
                }

                DB::table('role_menu_permissions')->updateOrInsert(
                    ['role_id' => $roles[$roleName], 'menu_id' => $menus[$key]],
                    [
                        'can_create' => false,
                        'can_read' => true,
                        'can_update' => false,
                        'can_delete' => false,
                        'can_detail' => true,
                    ]
                );
            }
        };

        foreach (['admin pusat', 'admin cabang'] as $roleName) {
            $setPermission($roleName, $reportKeys);
        }
    }
}
