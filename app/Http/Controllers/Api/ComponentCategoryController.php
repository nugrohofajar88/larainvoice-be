<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ComponentCategory;

class ComponentCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ComponentCategory::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id', 'name', 'created_at'];

        if (!in_array($sortBy, $allowed)) $sortBy = 'name';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $item = ComponentCategory::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        return response()->json($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:component_categories,name',
        ]);

        $item = ComponentCategory::create(['name' => $validated['name']]);

        return response()->json(['message' => 'Component category berhasil dibuat', 'data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $item = ComponentCategory::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:component_categories,name,' . $id,
        ]);

        if (array_key_exists('name', $validated)) $item->name = $validated['name'];
        $item->save();

        return response()->json(['message' => 'Component category berhasil diupdate', 'data' => $item]);
    }

    public function destroy(Request $request, $id)
    {
        $item = ComponentCategory::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $item->delete();
        return response()->json(['message' => 'Component category berhasil dihapus']);
    }
}
