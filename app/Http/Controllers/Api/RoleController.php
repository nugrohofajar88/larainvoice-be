<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use App\Traits\MenuPermissionHelper;

class RoleController extends Controller
{
    use MenuPermissionHelper;
    public function index(Request $request)
    {
        $query = Role::withCount('users');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where('name', 'like', "%{$s}%");
        }

        $filters = $request->only(['name']);
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, 'like', "%{$value}%");
            }
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id', 'name', 'created_at'];

        if (!in_array($sortBy, $allowed)) $sortBy = 'id';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $menuTemplate = $this->populateMenuPermission();
        $roleIds = $paginator->getCollection()->pluck('id');
        $allPermissions = DB::table('role_menu_permissions')->whereIn('role_id', $roleIds)->get()->groupBy('role_id');

        $paginator->getCollection()->transform(function ($role) use ($menuTemplate, $allPermissions) {
            $role->permissions = $this->joinRolePermission($menuTemplate, $allPermissions->get($role->id));
            return $role;
        });

        return response()->json($paginator);
    }

    /**
     * Mendapatkan daftar role tanpa paginasi (untuk dropdown/select)
     */
    public function list()
    {
        return response()->json(Role::orderBy('name')->get(['id', 'name']));
    }

    /**
     * Mendapatkan template menu kosong (semua false) untuk pembuatan role baru
     */
    public function getPermissionsTemplate()
    {
        return response()->json($this->populateMenuPermission());
    }

    public function show($id)
    {
        $role = Role::with(['users'])->withCount('users')->findOrFail($id);
        $menuTemplate = $this->populateMenuPermission();
        $actualPermissions = DB::table('role_menu_permissions')->where('role_id', $role->id)->get();

        $role->permissions = $this->joinRolePermission($menuTemplate, $actualPermissions);
        
        return response()->json($role);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*.id' => 'required_with:permissions|exists:menus,id',
            'permissions.*.view' => 'boolean',
            'permissions.*.create' => 'boolean',
            'permissions.*.edit' => 'boolean',
            'permissions.*.delete' => 'boolean',
            'permissions.*.detail' => 'boolean',
        ]);

        $role = DB::transaction(function () use ($validated) {
            $role = Role::create(['name' => $validated['name']]);

            if (!empty($validated['permissions'])) {
                $this->syncRolePermissions($role, $validated['permissions']);
            }

            return $role;
        });

        return response()->json($this->show($role->id)->original, 201);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'id' => 'sometimes|integer|in:' . $id,
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*.id' => 'required_with:permissions|exists:menus,id',
            'permissions.*.view' => 'boolean',
            'permissions.*.create' => 'boolean',
            'permissions.*.edit' => 'boolean',
            'permissions.*.delete' => 'boolean',
            'permissions.*.detail' => 'boolean',
        ]);

        DB::transaction(function () use ($role, $validated) {
            if (isset($validated['name'])) {
                $role->update(['name' => $validated['name']]);
            }

            if (isset($validated['permissions'])) {
                $this->syncRolePermissions($role, $validated['permissions']);
            }
        });

        return response()->json($this->show($role->id)->original);
    }

    public function destroy(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Cek apakah role yang akan dihapus adalah super admin
        if (isSuperAdminRole($role)) {
            return response()->json(
                ['message' => 'Tidak bisa menghapus role superadmin'],
                422
            );
        }

        // Cek apakah ada user yang menggunakan role ini
        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return response()->json(
                ['message' => 'Role sedang digunakan oleh ' . $userCount . ' user. Tidak dapat dihapus.'],
                422
            );
        }

        // Hard delete dalam transaksi dengan order: role_menu_permissions -> role_menus -> roles
        DB::transaction(function () use ($role, $id) {
            DB::table('role_menu_permissions')->where('role_id', $id)->delete();
            DB::table('role_menus')->where('role_id', $id)->delete();
            $role->forceDelete();
        });

        return response()->json(['message' => 'Role berhasil dihapus']);
    }

    /**
     * Helper untuk sinkronisasi permission dan menu mapping
     */
    private function syncRolePermissions($role, array $permissions)
    {
        // Bersihkan data lama
        DB::table('role_menu_permissions')->where('role_id', $role->id)->delete();
        DB::table('role_menus')->where('role_id', $role->id)->delete();

        foreach ($permissions as $p) {
            $view   = $p['view'] ?? false;
            $create = $p['create'] ?? false;
            $edit   = $p['edit'] ?? false;
            $delete = $p['delete'] ?? false;
            $detail = $p['detail'] ?? false;

            // Simpan jika setidaknya ada satu akses yang diberikan
            if ($view || $create || $edit || $delete || $detail) {
                DB::table('role_menu_permissions')->insert([
                    'role_id'    => $role->id,
                    'menu_id'    => $p['id'],
                    'can_read'   => $view,
                    'can_create' => $create,
                    'can_update' => $edit,
                    'can_delete' => $delete,
                    'can_detail' => $detail,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('role_menus')->insert([
                    'role_id' => $role->id,
                    'menu_id' => $p['id'],
                ]);
            }
        }
    }
}