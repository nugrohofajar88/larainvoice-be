<?php

namespace App\Http\Controllers\Api;

use App\Models\Supplier;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Supplier::with(['branch']);

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $filters = $request->only([
            'name',
            'contact_person',
            'email',
            'phone',
            'city',
            'branch_id',
        ]);

        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                if ($field === 'branch_id') {
                    $query->where($field, $value);
                } else {
                    $query->where($field, 'like', "%{$value}%");
                }
            }
        }

        if ($request->filled('branch')) {
            $branch = $request->input('branch');
            $query->whereHas('branch', function ($q) use ($branch) {
                $q->where('name', 'like', "%{$branch}%")
                    ->orWhere('city', 'like', "%{$branch}%");
            });
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));

        $allowedSorts = ['id', 'name', 'contact_person', 'email', 'phone', 'city', 'branch_id', 'created_at'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->input('per_page', 10), 100);

        if ((int) $request->input('per_page') === 9999) {
            return response()->json($query->get());
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $data = $validated;
        $data['branch_id'] = $user->isSuperAdmin() && isset($validated['branch_id']) ? $validated['branch_id'] : $user->branch_id;

        $supplier = Supplier::create($data);

        return response()->json([
            'message' => 'Supplier berhasil dibuat',
            'data' => $supplier->load(['branch'])
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $query = Supplier::with(['branch']);

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $supplier = $query->find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        return response()->json([
            'data' => $supplier
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json(['message' => 'Supplier tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $supplier->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke supplier cabang lain'], 403);
        }

        $validated = $request->validate([
            'branch_id' => $user->isSuperAdmin() ? 'nullable|exists:branches,id' : 'nullable',
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $data = $validated;
        if ($user->isSuperAdmin() && isset($validated['branch_id'])) {
            $data['branch_id'] = $validated['branch_id'];
        } else {
            // Keep existing branch_id
            unset($data['branch_id']);
        }

        $supplier->update($data);

        return response()->json([
            'message' => 'Supplier berhasil diupdate',
            'data' => $supplier->load(['branch'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier tidak ditemukan'
            ], 404);
        }

        if (!$user->isSuperAdmin() && $supplier->branch_id !== $user->branch_id) {
            return response()->json([
                'message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'
            ], 403);
        }
        
        $supplier->delete();

        return response()->json([
            'message' => 'Supplier berhasil dihapus'
        ]);
    }
}
