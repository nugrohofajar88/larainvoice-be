<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;
use App\Traits\MenuPermissionHelper;

class MenuController extends Controller
{
    use MenuPermissionHelper;
    /**
     * GET /api/menus
     * List semua menu dengan struktur tree
     */
    public function index(Request $request)
    {
        $sortBy = $request->input('sort_by', 'parent_id');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));

        $allowed = ['id', 'name', 'sort_order', 'parent_id', 'created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'parent_id';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        // Get root menus only (parent_id = null)
        $menus = Menu::whereNull('parent_id')
            ->with('children')
            ->orderBy($sortBy, $sortDir)
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json($menus);
    }

    /**
     * GET /api/menus/template
     * Get menu template dalam format tree dengan permissions default false
     * Digunakan untuk role permission assignment
     */
    public function template(Request $request)
    {
        return response()->json($this->populateMenuPermission());
    }

    /**
     * GET /api/menus/flat
     * List semua menu dalam bentuk flat (tidak tree)
     */
    public function flat(Request $request)
    {
        $query = Menu::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('key', 'like', "%{$search}%");
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active'));
        }

        $sortBy = $request->input('sort_by', 'sort_order');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));

        $allowed = ['id', 'name', 'sort_order', 'created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'sort_order';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        $perPage = min($request->input('per_page', 15), 100);

        $query->orderBy($sortBy, $sortDir);

        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/menus/{id}
     * Get detail menu dengan children
     */
    public function show($id)
    {
        $menu = Menu::with(['parent', 'children'])->find($id);

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        return response()->json($menu);
    }

    /**
     * POST /api/menus
     * Create menu baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:menus,id',
            'name' => 'required|string|max:255',
            'key' => 'required|string|unique:menus,key',
            'icon' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'key' => $validated['key'],
            'icon' => $validated['icon'] ?? null,
            'route' => $validated['route'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (array_key_exists('parent_id', $validated)) {
            $data['parent_id'] = $validated['parent_id'];
        }

        $menu = Menu::create($data);

        return response()->json([
            'message' => 'Menu berhasil dibuat',
            'data' => $menu->load(['parent', 'children'])
        ], 201);
    }

    /**
     * PUT /api/menus/{id}
     * Update menu
     */
    public function update(Request $request, $id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:menus,id|different:id',
            'name' => 'sometimes|string|max:255',
            'key' => 'sometimes|string|unique:menus,key,' . $id,
            'icon' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = [];
        if (array_key_exists('name', $validated)) $data['name'] = $validated['name'];
        if (array_key_exists('key', $validated)) $data['key'] = $validated['key'];
        if (array_key_exists('icon', $validated)) $data['icon'] = $validated['icon'];
        if (array_key_exists('route', $validated)) $data['route'] = $validated['route'];
        if (array_key_exists('sort_order', $validated)) $data['sort_order'] = $validated['sort_order'];
        if (array_key_exists('is_active', $validated)) $data['is_active'] = $validated['is_active'];
        if (array_key_exists('parent_id', $validated)) $data['parent_id'] = $validated['parent_id'];

        $menu->update($data);

        return response()->json([
            'message' => 'Menu berhasil diupdate',
            'data' => $menu->load(['parent', 'children'])
        ]);
    }

    /**
     * DELETE /api/menus/{id}
     * Delete menu
     */
    public function destroy($id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan'], 404);
        }

        // Cek apakah ada child menus
        $childCount = $menu->children()->count();
        if ($childCount > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus menu yang memiliki submenu. Hapus submenu terlebih dahulu.',
                'child_count' => $childCount
            ], 422);
        }

        // Cek apakah menu digunakan di role_menu_permissions
        $permissionCount = DB::table('role_menu_permissions')
            ->where('menu_id', $id)
            ->count();

        if ($permissionCount > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus menu yang sudah digunakan di permission. Hapus permission terlebih dahulu.',
                'permission_count' => $permissionCount
            ], 422);
        }

        $menu->delete();

        return response()->json(['message' => 'Menu berhasil dihapus']);
    }
}
