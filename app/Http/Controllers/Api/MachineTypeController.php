<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MachineType;

class MachineTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = MachineType::query();

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where('name', 'like', "%{$s}%");
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowed = ['id', 'name', 'created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'name';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        $perPage = min($request->input('per_page', 15), 100);

        $query->orderBy($sortBy, $sortDir);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $machineType = MachineType::find($id);
        if (!$machineType) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        return response()->json($machineType);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:machine_types,name',
        ]);

        $machineType = MachineType::create(['name' => $validated['name']]);

        return response()->json(['message' => 'Machine type berhasil dibuat', 'data' => $machineType], 201);
    }

    public function update(Request $request, $id)
    {
        $machineType = MachineType::find($id);
        if (!$machineType) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:machine_types,name,' . $id,
        ]);

        if (array_key_exists('name', $validated)) $machineType->name = $validated['name'];
        $machineType->save();

        return response()->json(['message' => 'Machine type berhasil diupdate', 'data' => $machineType]);
    }

    public function destroy(Request $request, $id)
    {
        $machineType = MachineType::find($id);
        if (!$machineType) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $machineType->delete();
        return response()->json(['message' => 'Machine type berhasil dihapus']);
    }
}
