<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SalesProfile;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $query = User::with(['role', 'branch', 'salesProfile']);

        // Only fetch users with 'sales' role
        $query->whereHas('role', function ($q) {
            $q->where('name', 'sales');
        });

        // If not superadmin, restrict to current user's branch
        if ($currentUser && !$currentUser->isSuperAdmin()) {
            $query->where('branch_id', $currentUser->branch_id);
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

        // column filters on users table
        $filters = $request->only(['name', 'username', 'email', 'branch_id']);
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                if ($field === 'branch_id') {
                    $query->where($field, $value);
                } else {
                    $query->where($field, 'like', "%{$value}%");
                }
            }
        }

        // filter by sales_profile fields
        if ($request->filled('nik')) {
            $nik = $request->input('nik');
            $query->whereHas('salesProfile', function ($q) use ($nik) {
                $q->where('nik', 'like', "%{$nik}%");
            });
        }

        // relation string filters
        if ($request->filled('branch_name')) {
            $branch = $request->input('branch_name');
            $query->whereHas('branch', function ($q) use ($branch) {
                $q->where('name', 'like', "%{$branch}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id', 'name', 'username', 'email', 'created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'name';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        // transform to merge user and sales_profile data
        $paginator = $paginator->through(function ($user) {
            $profile = $user->salesProfile;
            $user->nik = $profile?->nik;
            return $user;
        });

        return response()->json($paginator);
    }

    public function show(Request $request, $id)
    {
        $currentUser = $request->user();

        $query = User::with(['role', 'branch', 'salesProfile'])
            ->whereHas('role', function ($q) {
                $q->where('name', 'sales');
            });

        if ($currentUser && !$currentUser->isSuperAdmin()) {
            $query->where('branch_id', $currentUser->branch_id);
        }

        $user = $query->find($id);
        if (!$user) {
            return response()->json(['message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        $profile = $user->salesProfile;
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
            'branch' => $user->branch,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'nik' => $profile?->nik,
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();

        // Validate both user and sales_profile data
        $data = $request->validate([
            // User fields
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'branch_id' => $currentUser && $currentUser->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            // Sales profile fields
            'nik' => 'required|string|max:50',
        ]);

        // Get sales role id
        $salesRole = Role::where('name', 'sales')->firstOrFail();

        // Wrap in transaction for atomicity
        try {
            $user = DB::transaction(function () use ($data, $salesRole, $currentUser) {
                // Create user
                $userData = [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'email' => $data['email'] ?? null,
                    'password' => Hash::make($data['password']),
                    'role_id' => $salesRole->id,
                    'branch_id' => ($currentUser && $currentUser->isSuperAdmin()) ? $data['branch_id'] : $currentUser->branch_id,
                ];

                $user = User::create($userData);

                // Create sales profile
                $profileData = [
                    'user_id' => $user->id,
                    'nik' => $data['nik'],
                ];

                SalesProfile::create($profileData);

                return $user->load(['role', 'branch', 'salesProfile']);
            });

            return response()->json($user, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create sales: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $currentUser = $request->user();

        $user = User::whereHas('role', function ($q) {
            $q->where('name', 'sales');
        })->findOrFail($id);

        // If not superadmin, ensure same branch
        if ($currentUser && !$currentUser->isSuperAdmin() && $user->branch_id !== $currentUser->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke sales cabang lain'], 403);
        }

        // Validate data
        $data = $request->validate([
            // User fields
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'branch_id' => ($currentUser && $currentUser->isSuperAdmin()) ? 'sometimes|required|exists:branches,id' : 'nullable',
            // Sales profile fields
            'nik' => 'sometimes|required|string|max:50',
        ]);

        try {
            $user = DB::transaction(function () use ($user, $data, $currentUser) {
                // Update user fields
                $userData = [];
                foreach (['name', 'username', 'email'] as $field) {
                    if (isset($data[$field])) {
                        $userData[$field] = $data[$field];
                    }
                }

                // allow branch_id change only for superadmin
                if ($currentUser && $currentUser->isSuperAdmin() && array_key_exists('branch_id', $data)) {
                    $userData['branch_id'] = $data['branch_id'];
                }

                if (!empty($data['password'])) {
                    $userData['password'] = Hash::make($data['password']);
                }

                if (!empty($userData)) {
                    $user->update($userData);
                }

                // Update sales profile
                $profile = $user->salesProfile;
                if ($profile) {
                    $profileData = [];
                    foreach (['nik'] as $field) {
                        if (isset($data[$field])) {
                            $profileData[$field] = $data[$field];
                        }
                    }

                    if (!empty($profileData)) {
                        $profile->update($profileData);
                    }
                }

                return $user->load(['role', 'branch', 'salesProfile']);
            });

            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update sales: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();

        $user = User::whereHas('role', function ($q) {
            $q->where('name', 'sales');
        })->findOrFail($id);

        if ($currentUser && !$currentUser->isSuperAdmin() && $user->branch_id !== $currentUser->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'], 403);
        }

        // Record who deleted this
        $user->deleted_by = $currentUser?->id;
        $user->save();

        // Delete user (cascade will handle sales_profile)
        $user->delete();

        return response()->json(['message' => 'Sales staff deleted']);
    }
}
