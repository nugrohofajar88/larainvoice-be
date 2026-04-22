<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Traits\MenuPermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use MenuPermissionHelper;
    public function login(Request $request)
    {
        
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $user = User::with('role')->where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah'
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // =========================
        // GET MENUS (assigned to role)
        // =========================
        $query = DB::table('role_menus')
            ->join('menus', 'menus.id', '=', 'role_menus.menu_id')
            ->where('role_menus.role_id', $user->role_id);

        // Filter is_active = true jika user bukan super admin
        if (!isSuperAdmin($user)) {
            $query->where('menus.is_active', true);
        }

        $menus = $query->select('menus.*')
            ->orderBy('menus.sort_order')
            ->get();

        // Convert to tree structure
        $menuTree = $this->buildMenuTree($menus);
            
        // =========================
        // GET PERMISSIONS
        // =========================
        $permissionsRaw = DB::table('role_menu_permissions')
            ->where('role_id', $user->role_id)
            ->get();

        // Merge permissions into menu tree dan apply cascading filter
        // joinRolePermission expect: $template (tree), $actualData (permissions)
        $menuWithPermissions = $this->joinRolePermission($menuTree, $permissionsRaw)->values();

        // Also build flat permissions map for compatibility (menu_key => permissions)
        $permissions = [];
        foreach ($permissionsRaw as $p) {
            $menuKey = DB::table('menus')->where('id', $p->menu_id)->value('key');
            $permissions[$menuKey] = [
                'create' => (bool) $p->can_create,
                'read'   => (bool) $p->can_read,
                'update' => (bool) $p->can_update,
                'delete' => (bool) $p->can_delete,
                'detail' => (bool) $p->can_detail,
            ];
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
            'menus' => $menuWithPermissions,  // Tree with merged permissions & cascading filter applied
            'permissions' => $permissions,    // Flat key-value for compatibility
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    private function buildMenuTree($menus, $parentId = null)
    {
        $branch = [];

        foreach ($menus as $menu) {
            if ($menu->parent_id == $parentId) {
                $children = $this->buildMenuTree($menus, $menu->id);

                if ($children) {
                    $menu->children = $children;
                }

                $branch[] = $menu;
            }
        }

        return $branch;
    }
}
