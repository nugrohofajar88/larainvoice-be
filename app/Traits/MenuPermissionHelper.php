<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait MenuPermissionHelper
{
    /**
     * Menyiapkan daftar semua menu dengan permission default false (0)
     * Digunakan untuk role permission assignment template
     */
    public function populateMenuPermission()
    {
        // 1. Ambil semua data dari database, urutkan berdasarkan sort_order
        $menus = DB::table('menus')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'key as module', 'name', 'parent_id', 'sort_order'])
            ->map(fn($m) => [
                'id' => $m->id,
                'module' => $m->module,
                'name' => $m->name,
                'parent_id' => $m->parent_id,
                'sort_order' => $m->sort_order,
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
                'detail' => false,
                'children' => [] // Siapkan wadah untuk submenu
            ])
            ->toArray();

        // 2. Ubah menjadi struktur Tree
        return $this->buildMenuTree($menus);
    }

    /**
     * Build menu tree structure secara rekursif
     */
    public function buildMenuTree(array $elements, $parentId = null)
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                // Cari anak dari menu saat ini secara rekursif
                $children = $this->buildMenuTree($elements, $element['id']);
                
                if ($children) {
                    $element['children'] = $children;
                }

                $branch[] = $element;
            }
        }

        return $branch;
    }

    /**
     * Menggabungkan (merge) data izin aktual dari database ke dalam template menu
     * 
     * @param \Illuminate\Support\Collection $template Output dari populateMenuPermission
     * @param \Illuminate\Support\Collection|null $actualData Data dari tabel role_menu_permissions
     */
    public function joinRolePermission($template, $actualData)
    {
        $actualKeyed = collect($actualData)->keyBy('menu_id');

        // Helper rekursif untuk menyalin izin ke dalam tree menu
        $mergeFunc = function ($items) use (&$mergeFunc, $actualKeyed) {
            return array_map(function ($item) use (&$mergeFunc, $actualKeyed) {
                $item = (array) $item;
                $permission = $actualKeyed->get($item['id'] ?? null);

                if ($permission) {
                    $item['view']   = (bool) ($permission->can_read   ?? false);
                    $item['create'] = (bool) ($permission->can_create ?? false);
                    $item['edit']   = (bool) ($permission->can_update ?? false);
                    $item['delete'] = (bool) ($permission->can_delete ?? false);
                    $item['detail'] = (bool) ($permission->can_detail ?? false);
                }

                if (!empty($item['children'])) {
                    $item['children'] = $mergeFunc($item['children']);
                }

                return $item;
            }, (array) $items);
        };

        return collect($mergeFunc($template));
    }
}
