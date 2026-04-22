<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Helpers\PermissionHelper;

class CustomerController extends Controller
{
    // GET /api/customers?
    // search=PT
    // &branch=malang
    // &sales=andi
    // &sort_by=full_name
    // &sort_dir=asc
    // &per_page=5
    // &page=1
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Customer::with(['branch', 'sales']);

        // Sesuai Core Concept PRD: Admin Pusat/Administrator bisa akses semua cabang
        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        // =========================
        // 🔍 GLOBAL SEARCH
        // =========================
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // =========================
        // 🔍 COLUMN FILTER (DIRECT)
        // =========================
        $filters = $request->only([
            'full_name',
            'contact_name',
            'phone_number',
            'branch_id',
            'sales_id'
        ]);

        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {

                if (in_array($field, ['branch_id', 'sales_id'])) {
                    $query->where($field, $value);
                } else {
                    $query->where($field, 'like', "%{$value}%");
                }
            }
        }

        // =========================
        // 🔍 RELATION FILTER (STRING)
        // =========================
        if ($request->filled('branch')) {
            $branch = $request->input('branch');
            $query->whereHas('branch', function ($q) use ($branch) {
                $q->where('name', 'like', "%{$branch}%")
                ->orWhere('city', 'like', "%{$branch}%");
            });
        }

        if ($request->filled('sales')) {
            $sales = $request->input('sales');
            $query->whereHas('sales', function ($q) use ($sales) {
                $q->where('name', 'like', "%{$sales}%");
            });
        }

        // =========================
        // 🔃 SORTING (SAFE)
        // =========================
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));

        $allowedSorts = ['id', 'full_name', 'created_at'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        // =========================
        // 📄 PAGINATION
        // =========================
        $perPage = min($request->input('per_page', 10), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = Customer::with(['branch', 'sales']);

        // Jika bukan Super Admin, batasi query hanya pada cabang user tersebut
        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $customer = $query->find($id);

        if (!$customer) {
            return response()->json([
                'message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'
            ], 404);
        }

        return response()->json($customer);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Validasi dan ambil hanya field yang diizinkan
        $validated = $request->validate([
            'full_name' => 'required|string',
            'contact_name' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'sales_id' => 'nullable|exists:users,id',
        ]);

        $data = [
            'full_name' => $validated['full_name'],
            'contact_name' => $validated['contact_name'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'sales_id' => $validated['sales_id'] ?? null,
        ];

        // Tentukan branch secara aman: non-superadmin wajib menggunakan branch user
        $data['branch_id'] = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;

        $customer = Customer::create($data);

        return response()->json([
            'message' => 'Customer berhasil dibuat',
            'data' => $customer
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);
        $user = $request->user();

        if (!$customer) {
            return response()->json(['message' => 'Customer tidak ditemukan'], 404);
        }

        // Jika bukan Super Admin, pastikan customer yang diedit berasal dari cabang yang sama
        if (!$user->isSuperAdmin() && $customer->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke customer cabang lain'], 403);
        }
        
        $branchId = $user->branch_id;

        $validated = $request->validate([
            'full_name' => 'sometimes|string',
            'contact_name' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'branch_id'    => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'sales_id'     => 'nullable|exists:users,id',
        ]);

        $data = [];
        if (array_key_exists('full_name', $validated)) $data['full_name'] = $validated['full_name'];
        if (array_key_exists('contact_name', $validated)) $data['contact_name'] = $validated['contact_name'];
        if (array_key_exists('phone_number', $validated)) $data['phone_number'] = $validated['phone_number'];
        if (array_key_exists('address', $validated)) $data['address'] = $validated['address'];
        if (array_key_exists('sales_id', $validated)) $data['sales_id'] = $validated['sales_id'];

        // Proteksi branch: non-superadmin tidak boleh mengubah branch_id
        if ($user->isSuperAdmin() && array_key_exists('branch_id', $validated)) {
            $data['branch_id'] = $validated['branch_id'];
        } else {
            $data['branch_id'] = $customer->branch_id; // keep original
        }

        $customer->update($data);

        return response()->json([
            'message' => 'Customer berhasil diupdate',
            'data' => $customer->load(['branch', 'sales']) // Load relasi agar response lengkap
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer tidak ditemukan'
            ], 404);
        }

        // Pastikan user biasa tidak bisa hapus customer cabang lain
        if (!$user->isSuperAdmin() && $customer->branch_id !== $user->branch_id) {
            return response()->json([
                'message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'
            ], 403);
        }

        // Simpan siapa yang delete
        $customer->deleted_by = $user->id;
        $customer->save();

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus'
        ]);
    }
}
