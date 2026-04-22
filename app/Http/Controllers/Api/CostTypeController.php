<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CostType;
use Illuminate\Http\Request;

class CostTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = CostType::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id', 'name', 'created_at'];

        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'name';
        }

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $item = CostType::find($id);

        if (!$item) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cost_types,name'],
            'description' => ['nullable', 'string'],
        ]);

        $item = CostType::create($validated);

        return response()->json([
            'message' => 'Tipe biaya berhasil dibuat',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = CostType::find($id);

        if (!$item) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:cost_types,name,' . $id],
            'description' => ['nullable', 'string'],
        ]);

        $item->fill($validated);
        $item->save();

        return response()->json([
            'message' => 'Tipe biaya berhasil diupdate',
            'data' => $item,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $item = CostType::find($id);

        if (!$item) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Tipe biaya berhasil dihapus']);
    }
}
