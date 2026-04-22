<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Component;
use Illuminate\Support\Facades\DB;

class ComponentController extends Controller
{
    // GET /api/components
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Component::select('components.*')
            ->with(['branch', 'supplier', 'componentCategory'])
            ->leftJoin('branches', 'branches.id', '=', 'components.branch_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'components.supplier_id')
            ->leftJoin('component_categories', 'component_categories.id', '=', 'components.component_category_id');

        if (!$user->isSuperAdmin()) {
            $query->where('components.branch_id', $user->branch_id);
        } else {
            if ($request->filled('branch_id')) {
                $query->where('components.branch_id', $request->input('branch_id'));
            }
        }

        if ($request->filled('supplier_id')) {
            $query->where('components.supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('component_category_id')) {
            $query->where('components.component_category_id', $request->input('component_category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('components.name', 'like', "%{$search}%")
                    ->orWhere('components.type_size', 'like', "%{$search}%")
                    ->orWhere('components.weight', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));

        $allowedSorts = ['id', 'created_at', 'name', 'type_size', 'branch', 'supplier', 'component_category'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'name';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        if ($sortBy === 'branch') {
            $query->orderBy('branches.name', $sortDir);
        } elseif ($sortBy === 'supplier') {
            $query->orderBy('suppliers.name', $sortDir);
        } elseif ($sortBy === 'component_category') {
            $query->orderBy('component_categories.name', $sortDir);
        } elseif ($sortBy === 'name') {
            $query->orderBy('components.name', $sortDir)
                ->orderBy('components.type_size', $sortDir);
        } else {
            $query->orderBy('components.' . $sortBy, $sortDir);
        }

        $perPage = min($request->input('per_page', 15), 100);

        return response()->json(
            $query
                ->withCount(['stockMovements as qty' => function ($query) {
                    $query->select(DB::raw('SUM(qty)'));
                }])
                ->addSelect(['price_buy' => function ($query) {
                    $query->select('new_price')
                        ->from('component_price_histories')
                        ->whereColumn('component_price_histories.component_id', 'components.id')
                        ->where('type', 'BUY')
                        ->orderByDesc('id')
                        ->limit(1);
                }])
                ->addSelect(['price_sell' => function ($query) {
                    $query->select('new_price')
                        ->from('component_price_histories')
                        ->whereColumn('component_price_histories.component_id', 'components.id')
                        ->where('type', 'SELL')
                        ->orderByDesc('id')
                        ->limit(1);
                }])
                ->addSelect(['branch_name' => function ($query) {
                    $query->select('name')
                        ->from('branches')
                        ->whereColumn('branches.id', 'components.branch_id')
                        ->limit(1);
                }])
                ->addSelect(['supplier_name' => function ($query) {
                    $query->select('name')
                        ->from('suppliers')
                        ->whereColumn('suppliers.id', 'components.supplier_id')
                        ->limit(1);
                }])
                ->addSelect(['component_category_name' => function ($query) {
                    $query->select('name')
                        ->from('component_categories')
                        ->whereColumn('component_categories.id', 'components.component_category_id')
                        ->limit(1);
                }])
                ->paginate($perPage)
        );
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = Component::with(['branch', 'supplier', 'componentCategory']);

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $component = $query->find($id);
        if (!$component) {
            return response()->json(['message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        $component->qty = DB::table('component_stock_movements')
            ->where('component_id', $id)
            ->sum('qty');

        $component->price_buy = DB::table('component_price_histories')
            ->where('component_id', $id)
            ->where('type', 'BUY')
            ->orderByDesc('id')
            ->value('new_price');

        $component->price_sell = DB::table('component_price_histories')
            ->where('component_id', $id)
            ->where('type', 'SELL')
            ->orderByDesc('id')
            ->value('new_price');

        $component->branch_name = DB::table('branches')
            ->where('id', $component->branch_id)
            ->value('name');

        $component->supplier_name = DB::table('suppliers')
            ->where('id', $component->supplier_id)
            ->value('name');

        $component->component_category_name = DB::table('component_categories')
            ->where('id', $component->component_category_id)
            ->value('name');

        return response()->json($component);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'existing_component_id' => 'nullable|integer|exists:components,id',
            'name' => 'required|string|max:255',
            'type_size' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'component_category_id' => 'nullable|exists:component_categories,id',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'qty' => 'required|integer',
            'price_buy' => 'nullable|numeric|min:0',
            'price_sell' => 'required|numeric|min:0',
        ]);

        $branchId = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;
        $componentName = trim((string) $validated['name']);

        DB::beginTransaction();

        try {
            $component = null;
            $existingComponentId = $validated['existing_component_id'] ?? null;

            if ($existingComponentId) {
                $component = Component::find($existingComponentId);

                if (!$component || (int) $component->branch_id !== (int) $branchId) {
                    DB::rollBack();
                    return response()->json(['message' => 'Component existing tidak valid untuk cabang yang dipilih'], 422);
                }
            }

            if (!$component) {
                $component = Component::where('branch_id', $branchId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($componentName)])
                    ->first();
            }

            $isExistingComponent = (bool) $component;

            if (!$component) {
                $component = Component::create([
                    'name' => $componentName,
                    'type_size' => $validated['type_size'] ?? null,
                    'weight' => $validated['weight'] ?? null,
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'component_category_id' => $validated['component_category_id'] ?? null,
                    'branch_id' => $branchId,
                ]);
            }

            if ((int) $request->input('qty') !== 0) {
                DB::table('component_stock_movements')->insert([
                    'component_id' => $component->id,
                    'qty' => $request->input('qty'),
                    'user_id' => $user->id,
                    'type' => 'IN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($isExistingComponent) {
                $this->syncPriceHistory($component->id, 'BUY', $request->input('price_buy'), $user->id);
                $this->syncPriceHistory($component->id, 'SELL', $request->input('price_sell'), $user->id);
            } else {
                DB::table('component_price_histories')->insert([
                    [
                        'component_id' => $component->id,
                        'old_price' => 0,
                        'new_price' => $request->input('price_buy'),
                        'type' => 'BUY',
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'component_id' => $component->id,
                        'old_price' => 0,
                        'new_price' => $request->input('price_sell'),
                        'type' => 'SELL',
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => $isExistingComponent
                    ? 'Stock dan harga component existing berhasil diproses'
                    : 'Component baru berhasil ditambahkan',
                'data' => $component->load(['branch', 'supplier', 'componentCategory'])
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyimpan component: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $component = Component::find($id);

        if (!$component) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $component->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke data cabang lain'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type_size' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'component_category_id' => 'nullable|exists:component_categories,id',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'qty' => 'required|integer',
            'price_buy' => 'nullable|numeric|min:0',
            'price_sell' => 'required|numeric|min:0',
        ]);

        $data = [];
        if (array_key_exists('name', $validated)) $data['name'] = $validated['name'];
        if (array_key_exists('type_size', $validated)) $data['type_size'] = $validated['type_size'];
        if (array_key_exists('weight', $validated)) $data['weight'] = $validated['weight'];
        if (array_key_exists('supplier_id', $validated)) $data['supplier_id'] = $validated['supplier_id'];
        if (array_key_exists('component_category_id', $validated)) $data['component_category_id'] = $validated['component_category_id'];

        if ($user->isSuperAdmin() && array_key_exists('branch_id', $validated)) {
            $data['branch_id'] = $validated['branch_id'];
        } else {
            $data['branch_id'] = $component->branch_id;
        }

        $component->update($data);

        $totalQty = DB::table('component_stock_movements')
            ->where('component_id', $id)
            ->sum('qty');

        $qtyDifference = $request->input('qty') - $totalQty;
        if ($qtyDifference !== 0) {
            DB::table('component_stock_movements')->insert([
                'component_id' => $id,
                'qty' => $qtyDifference,
                'user_id' => $user->id,
                'type' => 'ADJUSTMENT',
                'description' => 'STOCK OPNAME',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $lastBuyPrice = DB::table('component_price_histories')
            ->where('component_id', $id)
            ->where('type', 'BUY')
            ->orderByDesc('id')
            ->value('new_price') ?? 0;

        $lastSellPrice = DB::table('component_price_histories')
            ->where('component_id', $id)
            ->where('type', 'SELL')
            ->orderByDesc('id')
            ->value('new_price') ?? 0;

        if ($request->input('price_buy') != $lastBuyPrice) {
            DB::table('component_price_histories')->insert([
                'component_id' => $id,
                'old_price' => $lastBuyPrice,
                'new_price' => $request->input('price_buy'),
                'type' => 'BUY',
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($request->input('price_sell') != $lastSellPrice) {
            DB::table('component_price_histories')->insert([
                'component_id' => $id,
                'old_price' => $lastSellPrice,
                'new_price' => $request->input('price_sell'),
                'type' => 'SELL',
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Component berhasil diupdate',
            'data' => $component->load(['branch', 'supplier', 'componentCategory'])
        ]);
    }

    private function syncPriceHistory(int $componentId, string $type, $newPrice, int $userId): void
    {
        $lastPrice = DB::table('component_price_histories')
            ->where('component_id', $componentId)
            ->where('type', $type)
            ->orderByDesc('id')
            ->value('new_price');

        if ((string) $newPrice === (string) $lastPrice) {
            return;
        }

        DB::table('component_price_histories')->insert([
            'component_id' => $componentId,
            'old_price' => $lastPrice ?? 0,
            'new_price' => $newPrice,
            'type' => $type,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $component = Component::find($id);

        if (!$component) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $component->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'], 403);
        }

        $component->delete();

        return response()->json(['message' => 'Component berhasil dihapus']);
    }
}
