<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Size;

class SizeController extends Controller
{
    public function index(Request $request)
    {
        $query = Size::query();

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where('value', 'like', "%{$s}%");
        }

        $sortBy = $request->input('sort_by', 'value');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id', 'value', 'created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'value';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        $perPage = min($request->input('per_page', 15), 100);

        $query->orderBy($sortBy, $sortDir);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $size = Size::find($id);
        if (!$size) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        return response()->json($size);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'value' => 'required|string|unique:sizes,value',
        ]);

        $size = Size::create(['value' => $validated['value']]);

        return response()->json(['message' => 'Size berhasil dibuat', 'data' => $size], 201);
    }

    public function update(Request $request, $id)
    {
        $size = Size::find($id);
        if (!$size) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $validated = $request->validate([
            'value' => 'sometimes|string|unique:sizes,value,' . $id,
        ]);

        if (array_key_exists('value', $validated)) $size->value = $validated['value'];
        $size->save();

        return response()->json(['message' => 'Size berhasil diupdate', 'data' => $size]);
    }

    public function destroy(Request $request, $id)
    {
        $size = Size::find($id);
        if (!$size) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $size->delete();
        return response()->json(['message' => 'Size berhasil dihapus']);
    }
}
