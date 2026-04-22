<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuttingPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CuttingPriceController extends Controller
{
    public function getMulti(Request $request)
    {
        $machineTypeId = $request->input('machine_type_id');
        $plateTypeId = $request->input('plate_type_id');

        if (!$machineTypeId || !$plateTypeId) {
            return response()->json(['message' => 'machine_type_id and plate_type_id are required'], 400);
        }

        $items = CuttingPrice::where('machine_type_id', $machineTypeId)
            ->where('plate_type_id', $plateTypeId)
            ->with(['size'])
            ->get();

        return response()->json($items);
    }

    public function batchUpdate(Request $request)
    {
        $items = $request->input('items', []);

        if (empty($items)) {
            return response()->json(['message' => 'No items provided'], 400);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $price = CuttingPrice::find($item['id']);
                if (!$price) continue;

                $price->update([
                    'price_easy' => $item['price_easy'] ?? 0,
                    'price_medium' => $item['price_medium'] ?? 0,
                    'price_difficult' => $item['price_difficult'] ?? 0,
                    'price_per_minute' => $item['price_per_minute'] ?? 0,
                    'discount_pct' => $item['discount_pct'] ?? 0,
                    'is_active' => $item['is_active'] ?? true,
                ]);
            }
            
            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['message' => 'Batch update successful']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['message' => 'Batch update failed: ' . $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $query = CuttingPrice::query()
            ->with(['machineType', 'plateType', 'size'])
            ->leftJoin('machine_types', 'machine_types.id', '=', 'cutting_prices.machine_type_id')
            ->leftJoin('plate_types', 'plate_types.id', '=', 'cutting_prices.plate_type_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'cutting_prices.size_id')
            ->select('cutting_prices.*');

        $query->where('cutting_prices.is_active', true)
            ->whereNull('machine_types.deleted_at');

        if ($request->filled('machine_type_id')) {
            $query->where('cutting_prices.machine_type_id', $request->input('machine_type_id'));
        }

        if ($request->filled('plate_type_id')) {
            $query->where('cutting_prices.plate_type_id', $request->input('plate_type_id'));
        }

        if ($request->filled('size_id')) {
            $query->where('cutting_prices.size_id', $request->input('size_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('machine_types.name', 'like', "%{$search}%")
                    ->orWhere('plate_types.name', 'like', "%{$search}%")
                    ->orWhere('sizes.value', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'plate_type');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));
        $allowedSorts = [
            'id',
            'created_at',
            'machine_type',
            'plate_type',
            'size',
            'price_easy',
            'price_medium',
            'price_difficult',
            'price_per_minute',
            'discount_pct',
        ];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'plate_type';
        }

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }

        if ($sortBy === 'machine_type') {
            $query->orderBy('machine_types.name', $sortDir);
        } elseif ($sortBy === 'plate_type') {
            $query->orderBy('plate_types.name', $sortDir);
            $query->orderBy('sizes.value', $sortDir);
            $query->orderBy('machine_types.name', $sortDir);
        } elseif ($sortBy === 'size') {
            $query->orderBy('sizes.value', $sortDir);
            $query->orderBy('plate_types.name', $sortDir);
            $query->orderBy('machine_types.name', $sortDir);
        } else {
            $query->orderBy('cutting_prices.' . $sortBy, $sortDir);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show($id)
    {
        $item = CuttingPrice::with(['machineType', 'plateType', 'size'])->find($id);

        if (!$item) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json($item);
    }

    public function store(Request $request)
    {
        $items = $request->input('items');

        if (is_array($items) && count($items) > 0) {
            $machineTypeId = $request->input('machine_type_id');
            $plateTypeId = $request->input('plate_type_id');

            if (!$machineTypeId || !$plateTypeId) {
                return response()->json(['message' => 'Machine type and Plate type are required'], 400);
            }

            \Illuminate\Support\Facades\DB::beginTransaction();
            try {
                $createdCount = 0;
                foreach ($items as $itemData) {
                    $payload = array_merge($itemData, [
                        'machine_type_id' => $machineTypeId,
                        'plate_type_id' => $plateTypeId,
                        'is_active' => true // Default for new items
                    ]);
                    
                    // Validation & Duplicate check
                    $tempRequest = new Request($payload);
                    $validated = $this->validatePayload($tempRequest);
                    
                    CuttingPrice::create($validated);
                    $createdCount++;
                }
                
                \Illuminate\Support\Facades\DB::commit();
                return response()->json([
                    'message' => "{$createdCount} data harga cutting berhasil ditambahkan",
                ], 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json([
                    'message' => 'Validasi gagal pada salah satu item: ' . collect($e->errors())->flatten()->first(),
                    'errors' => $e->errors()
                ], 422);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['message' => 'Gagal simpan massal: ' . $e->getMessage()], 500);
            }
        }

        // Fallback to single insert
        $validated = $this->validatePayload($request);
        $item = CuttingPrice::create($validated);

        return response()->json([
            'message' => 'Cutting price berhasil dibuat',
            'data' => $item->load(['machineType', 'plateType', 'size']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = CuttingPrice::find($id);

        if (!$item) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $validated = $this->validatePayload($request, $item, true);

        $item->update($validated);

        return response()->json([
            'message' => 'Cutting price berhasil diupdate',
            'data' => $item->load(['machineType', 'plateType', 'size']),
        ]);
    }

    public function destroy($id)
    {
        $item = CuttingPrice::find($id);

        if (!$item) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Cutting price berhasil dihapus']);
    }

    private function validatePayload(Request $request, ?CuttingPrice $existing = null, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';
        $machineTypeId = $request->input('machine_type_id', $existing?->machine_type_id);
        $plateTypeId = $request->input('plate_type_id', $existing?->plate_type_id);
        $sizeId = $request->input('size_id', $existing?->size_id);

        $validated = $request->validate([
            'machine_type_id' => [
                $required,
                'exists:machine_types,id',
            ],
            'plate_type_id' => [$required, 'exists:plate_types,id'],
            'size_id' => [$required, 'exists:sizes,id'],
            'price_easy' => [$required, 'numeric', 'min:0'],
            'price_medium' => [$required, 'numeric', 'min:0'],
            'price_difficult' => [$required, 'numeric', 'min:0'],
            'price_per_minute' => [$required, 'numeric', 'min:0'],
            'discount_pct' => [$required, 'numeric', 'min:0', 'max:100'],
        ]);

        $duplicateExists = CuttingPrice::query()
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->where('machine_type_id', $machineTypeId)
            ->where('plate_type_id', $plateTypeId)
            ->where('size_id', $sizeId)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'machine_type_id' => ['Kombinasi machine type, plate type, dan size sudah ada.'],
            ]);
        }

        return $validated;
    }
}
