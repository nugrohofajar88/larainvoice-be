<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next, $permissionKey)
    {
        $user = $request->user();

        $roleId = $user->role_id;

        $menu = DB::table('menus')->where('key', $permissionKey)->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $permission = DB::table('role_menu_permissions')
            ->where('role_id', $roleId)
            ->where('menu_id', $menu->id)
            ->first();

        if (!$permission || !$permission->can_read || !$permission->can_detail) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
