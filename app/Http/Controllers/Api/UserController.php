<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $query = User::with(['role','branch']);

        // Exclude sales role users - sales dikelola di SalesController
        $query->whereHas('role', function ($q) {
            $q->where('name', '!=', 'sales');
        });

        // If not superadmin, restrict to current user's branch
        if ($currentUser && !$currentUser->isSuperAdmin()) {
            $query->where('branch_id', $currentUser->branch_id);
        }

        if ($currentUser && !$currentUser->isAdmin()) {
            $query->where('id', $currentUser->id);
        }

        // global search
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('username', 'like', "%{$s}%");
            });
        }

        // column filters
        $filters = $request->only(['name','username','email','role_id','branch_id']);
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                if (in_array($field, ['role_id','branch_id'])) {
                    $query->where($field, $value);
                } else {
                    $query->where($field, 'like', "%{$value}%");
                }
            }
        }

        // relation string filters similar to CustomerController
        if ($request->filled('branch_name')) {
            $branch = $request->input('branch_name');
            $query->whereHas('branch', function ($q) use ($branch) {
                $q->where('name', 'like', "%{$branch}%");
            });
        }

        if ($request->filled('role_name')) {
            $role = $request->input('role_name');
            $query->whereHas('role', function ($q) use ($role) {
                $q->where('name', 'like', "%{$role}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id','name','username','email','created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'name';
        if (!in_array($sortDir, ['asc','desc'])) $sortDir = 'asc';

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        // transform items to include role_name and branch_name for convenience
        $paginator->getCollection()->transform(function ($user) {
            $user->role_name = $user->role?->name ?? null;
            $user->branch_name = $user->branch?->name ?? null;
            // also expose minimal branch info
            $user->branch_info = $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
                'city' => $user->branch->city ?? null,
            ] : null;
            return $user;
        });

        return response()->json($paginator);
    }

    public function show(Request $request, $id)
    {
        $currentUser = $request->user();

        $query = User::with(['role','branch'])->findOrFail($id);

        if ($currentUser && !$currentUser->isSuperAdmin()) {
            $query->where('branch_id', $currentUser->branch_id);
        }

        $user = $query->find($id);
        if (!$user) {
            return response()->json(['message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        return response()->json($user);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json($user->load(['role','branch']), 201);
    }

    public function update(Request $request, $id)
    {
        $currentUser = $request->user();

        $user = User::findOrFail($id);

        // If not superadmin, ensure same branch
        if ($currentUser && !$currentUser->isSuperAdmin() && $user->branch_id !== $currentUser->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke sales cabang lain'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role_id' => 'sometimes|required|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->load(['role','branch']));
    }

    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();

        $user = User::findOrFail($id);

        if ($currentUser && !$currentUser->isSuperAdmin() && $user->branch_id !== $currentUser->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'], 403);
        }

        // Mencatat siapa yang menghapus user ini
        $user->deleted_by = $request->user()?->id;
        $user->save();

        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
