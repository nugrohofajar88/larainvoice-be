<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BranchInvoiceCounter;

class BranchInvoiceCounterController extends Controller
{
    // list counters (filter by branch)
    public function index(Request $request)
    {
        $user = $request->user();
        $query = BranchInvoiceCounter::with('branch');

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } else if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = BranchInvoiceCounter::with('branch');
        if (!$user->isSuperAdmin()) $query->where('branch_id', $user->branch_id);

        $item = $query->find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan atau tidak memiliki akses'], 404);
        return response()->json($item);
    }

    // create or upsert for current month/year
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'prefix' => 'nullable|string',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000',
            'last_number' => 'nullable|integer|min:0',
        ]);

        $branchId = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;

        $item = BranchInvoiceCounter::updateOrCreate(
            ['branch_id' => $branchId, 'month' => $validated['month'], 'year' => $validated['year']],
            ['prefix' => $validated['prefix'] ?? null, 'last_number' => $validated['last_number'] ?? 0]
        );

        return response()->json(['message' => 'Counter disimpan', 'data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $item = BranchInvoiceCounter::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        if (!$user->isSuperAdmin() && $item->branch_id !== $user->branch_id) return response()->json(['message'=>'Forbidden'], 403);

        $validated = $request->validate([
            'prefix' => 'nullable|string',
            'last_number' => 'nullable|integer|min:0',
        ]);

        if (array_key_exists('prefix', $validated)) $item->prefix = $validated['prefix'];
        if (array_key_exists('last_number', $validated)) $item->last_number = $validated['last_number'];
        $item->save();

        return response()->json(['message' => 'Counter diupdate', 'data' => $item]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $item = BranchInvoiceCounter::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        if (!$user->isSuperAdmin() && $item->branch_id !== $user->branch_id) return response()->json(['message'=>'Forbidden'], 403);

        $item->delete();
        return response()->json(['message' => 'Counter dihapus']);
    }
}
