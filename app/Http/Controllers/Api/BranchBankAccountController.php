<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BranchBankAccount;

class BranchBankAccountController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = BranchBankAccount::with('branch');

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } else {
            if ($request->filled('branch_id')) $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function($q) use ($s){
                $q->where('bank_name', 'like', "%{$s}%")
                  ->orWhere('account_number', 'like', "%{$s}%")
                  ->orWhere('account_holder', 'like', "%{$s}%");
            });
        }

        $perPage = min($request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = BranchBankAccount::with('branch');
        if (!$user->isSuperAdmin()) $query->where('branch_id', $user->branch_id);

        $item = $query->find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan atau tidak memiliki akses'], 404);
        return response()->json($item);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_holder' => 'required|string',
            'bank_code' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
        ]);

        $data = [
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder' => $validated['account_holder'],
            'bank_code' => $validated['bank_code'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
        ];

        $data['branch_id'] = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;

        $item = BranchBankAccount::create($data);

        return response()->json(['message' => 'Branch bank account dibuat', 'data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $item = BranchBankAccount::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if (!$user->isSuperAdmin() && $item->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'bank_name' => 'sometimes|string',
            'account_number' => 'sometimes|string',
            'account_holder' => 'sometimes|string',
            'bank_code' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
        ]);

        $data = [];
        foreach (['bank_name','account_number','account_holder','bank_code','is_default'] as $f) {
            if (array_key_exists($f, $validated)) $data[$f] = $validated[$f];
        }

        if ($user->isSuperAdmin() && array_key_exists('branch_id', $validated)) {
            $data['branch_id'] = $validated['branch_id'];
        } else {
            $data['branch_id'] = $item->branch_id;
        }

        $item->update($data);

        return response()->json(['message' => 'Branch bank account diupdate', 'data' => $item]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $item = BranchBankAccount::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if (!$user->isSuperAdmin() && $item->branch_id !== $user->branch_id) return response()->json(['message'=>'Forbidden'], 403);

        $item->delete();
        return response()->json(['message' => 'Branch bank account dihapus']);
    }
}
