<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, $menuKey, $action = 'read')
    {
        $user = $request->user();

        // 🔥 SUPER ADMIN BYPASS
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // 🔍 Ambil menu_id dari key
        $menu = DB::table('menus')->where('key', $menuKey)->first();

        if (!$menu) {
            return response()->json([
                'message' => 'Menu tidak ditemukan'
            ], 403);
        }

        // 🔍 Ambil permission
        $permission = DB::table('role_menu_permissions')
            ->where('role_id', $user->role_id)
            ->where('menu_id', $menu->id)
            ->first();

        if (!$permission) {
            return response()->json([
                'message' => 'Tidak punya akses'
            ], 403);
        }

        // 🔥 Mapping action
        $allowed = match ($action) {
            'create' => $permission->can_create,
            'read'   => $permission->can_read,
            'update' => $permission->can_update,
            'delete' => $permission->can_delete,
            'detail' => $permission->can_detail,
            default  => false
        };

        if (!$allowed) {
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        return $next($request);
    }
}