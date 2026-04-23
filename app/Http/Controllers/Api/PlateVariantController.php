<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlateVariant;
use Illuminate\Support\Facades\DB;
use App\Models\StockMovement;
use App\Models\PlatePriceHistory;
use App\Services\MobileNotificationService;
use Illuminate\Support\Facades\Log;

class PlateVariantController extends Controller
{
    // GET /api/plate-variants/multi
    public function getMulti(Request $request)
    {
        $user = $request->user();
        $branchId = $request->input('branch_id');
        $plateTypeId = $request->input('plate_type_id');

        if (!$branchId || !$plateTypeId) {
            return response()->json(['message' => 'branch_id and plate_type_id are required'], 400);
        }

        if (!$user->isSuperAdmin() && $branchId != $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $variants = PlateVariant::where('branch_id', $branchId)
            ->where('plate_type_id', $plateTypeId)
            ->with(['size'])
            ->get();

        $data = $variants->map(function ($v) {
            return [
                'id' => $v->id,
                'size_id' => $v->size_id,
                'size_name' => $v->size->name ?? $v->size->value,
                'price_buy' => DB::table('plate_price_histories')
                    ->where('plate_variant_id', $v->id)->where('type', 'BUY')->orderByDesc('id')->value('new_price') ?? 0,
                'price_sell' => DB::table('plate_price_histories')
                    ->where('plate_variant_id', $v->id)->where('type', 'SELL')->orderByDesc('id')->value('new_price') ?? 0,
                'qty' => DB::table('stock_movements')->where('plate_variant_id', $v->id)->sum('qty'),
                'is_active' => (bool)$v->is_active
            ];
        });

        return response()->json($data);
    }

    // PUT /api/plate-variants/batch
    public function batchUpdate(Request $request)
    {
        $user = $request->user();
        $items = $request->input('items', []);

        if (empty($items)) {
            return response()->json(['message' => 'No items provided'], 400);
        }

        DB::beginTransaction();
        try {
            $affectedVariantIds = [];

            foreach ($items as $item) {
                $variant = PlateVariant::find($item['id']);
                if (!$variant) continue;

                // Authorization check
                if (!$user->isSuperAdmin() && $variant->branch_id != $user->branch_id) continue;

                // 0. Update model fields (like is_active) if provided
                if (isset($item['is_active'])) {
                    $variant->update(['is_active' => filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN)]);
                }

                // 1. Process Stock Adjustment
                $currentQty = DB::table('stock_movements')->where('plate_variant_id', $variant->id)->sum('qty');
                $newQty = $item['qty'] ?? 0;
                if ($newQty != $currentQty) {
                    DB::table('stock_movements')->insert([
                        'plate_variant_id' => $variant->id,
                        'qty' => $newQty - $currentQty,
                        'user_id' => $user->id,
                        'type' => 'ADJUSTMENT',
                        'description' => 'STOCK OPNAME',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $affectedVariantIds[] = $variant->id;
                }

                // 2. Process Price Changes
                $this->updatePriceHistory($variant->id, 'BUY', $item['price_buy'] ?? 0, $user->id);
                $this->updatePriceHistory($variant->id, 'SELL', $item['price_sell'] ?? 0, $user->id);
            }

            DB::commit();

            $notificationService = app(MobileNotificationService::class);
            PlateVariant::with(['branch.setting', 'plateType', 'size'])
                ->whereIn('id', array_unique($affectedVariantIds))
                ->get()
                ->each(fn (PlateVariant $variant) => $notificationService->notifyLowStockForPlateVariant($variant));

            return response()->json(['message' => 'Batch update successful']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Batch update failed: ' . $e->getMessage()], 500);
        }
    }

    private function updatePriceHistory($variantId, $type, $newPrice, $userId)
    {
        $lastPrice = DB::table('plate_price_histories')
            ->where('plate_variant_id', $variantId)
            ->where('type', $type)
            ->orderByDesc('id')
            ->value('new_price') ?? 0;

        if ($newPrice != $lastPrice) {
            DB::table('plate_price_histories')->insert([
                'plate_variant_id' => $variantId,
                'old_price' => $lastPrice,
                'new_price' => $newPrice,
                'type' => $type,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // GET /api/plate-variants
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PlateVariant::select('plate_variants.*')
            ->with(['branch', 'plateType', 'size'])
            ->leftJoin('plate_types', 'plate_types.id', '=', 'plate_variants.plate_type_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'plate_variants.size_id');

        $query->where('plate_variants.is_active', true);

        if (!$user->isSuperAdmin()) {
            $query->where('plate_variants.branch_id', $user->branch_id);
        } else {
            if ($request->filled('branch_id')) {
                $query->where('plate_variants.branch_id', $request->input('branch_id'));
            }
        }

        if ($request->filled('plate_type_id')) {
            $query->where('plate_variants.plate_type_id', $request->input('plate_type_id'));
        }

        if ($request->filled('size_id')) {
            $query->where('plate_variants.size_id', $request->input('size_id'));
        }

        $sortBy = $request->input('sort_by', 'plate_type');
        $sortDir = strtolower($request->input('sort_dir', 'asc'));

        $allowedSorts = ['id', 'created_at', 'plate_type', 'size'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'id';
        if (!in_array($sortDir, ['asc', 'desc'])) $sortDir = 'asc';

        if ($sortBy === 'plate_type') {
            $query->orderBy('plate_types.name', $sortDir);
            $query->orderBy('sizes.value', $sortDir);
        } elseif ($sortBy === 'size') {
            $query->orderBy('sizes.value', $sortDir);
            $query->orderBy('plate_types.name', $sortDir);
        } else {
            $query->orderBy('plate_variants.' . $sortBy, $sortDir);
        }

        $perPage = min($request->input('per_page', 15), 100);

        return response()->json($query->withCount(['stockMovements as qty' => function ($query) {
            $query->select(DB::raw('SUM(qty)'));
        }])->addSelect(['price_sell' => function ($query) {
            $query->select('new_price')
                ->from('plate_price_histories')
                ->whereColumn('plate_price_histories.plate_variant_id', 'plate_variants.id')
                ->where('type', 'SELL')
                ->orderByDesc('id')
                ->limit(1);
        }])->addSelect(['price_buy' => function ($query) {
            $query->select('new_price')
                ->from('plate_price_histories')
                ->whereColumn('plate_price_histories.plate_variant_id', 'plate_variants.id')
                ->where('type', 'BUY')
                ->orderByDesc('id')
                ->limit(1);
        }])->addSelect(['branch_name' => function ($query) {
            $query->select('name')
                ->from('branches')
                ->whereColumn('branches.id', 'plate_variants.branch_id')
                ->limit(1);
        }])->addSelect(['plate_type_name' => function ($query) {
            $query->select('name')
                ->from('plate_types')
                ->whereColumn('plate_types.id', 'plate_variants.plate_type_id')
                ->limit(1);
        }])->addSelect(['size_value' => function ($query) {
            $query->select('value')
                ->from('sizes')
                ->whereColumn('sizes.id', 'plate_variants.size_id')
                ->limit(1);
        }])->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = PlateVariant::with(['branch', 'plateType', 'size']);

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $variant = $query->find($id);
        if (!$variant) {
            return response()->json(['message' => 'Data tidak ditemukan atau Anda tidak memiliki akses'], 404);
        }

        $variant->qty = DB::table('stock_movements')
            ->where('plate_variant_id', $id)
            ->sum('qty');

        $variant->price_sell = DB::table('plate_price_histories')
            ->where('plate_variant_id', $id)
            ->where('type', 'SELL')
            ->orderByDesc('id')
            ->value('new_price');

        $variant->price_buy = DB::table('plate_price_histories')
            ->where('plate_variant_id', $id)
            ->where('type', 'BUY')
            ->orderByDesc('id')
            ->value('new_price');

        $variant->branch_name = DB::table('branches')
            ->where('id', $variant->branch_id)
            ->value('name');

        $variant->plate_type_name = DB::table('plate_types')
            ->where('id', $variant->plate_type_id)
            ->value('name');

        $variant->size_value = DB::table('sizes')
            ->where('id', $variant->size_id)
            ->value('value');

        return response()->json($variant);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'plate_type_id' => 'required|exists:plate_types,id',
            'size_id' => 'required|exists:sizes,id',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'is_active'     => 'sometimes|boolean', // Pastikan divalidasi sebagai boolean
        ]);

        $branchId = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;

        // Gunakan updateOrCreate agar is_active selalu sinkron baik data baru maupun lama
        $variant = PlateVariant::updateOrCreate(
            [
                'branch_id'     => $branchId,
                'plate_type_id' => $validated['plate_type_id'],
                'size_id'       => $validated['size_id']
            ],
            [
                'is_active'     => $request->boolean('is_active', true)
            ]
        );

        // Jika sudah ada, tambahkan stok baru
        DB::table('stock_movements')->insert([
            'plate_variant_id' => $variant->id,
            'qty' => $request->input('qty'),
            'user_id' => $user->id, // Ensure the logged-in user's ID is used
            'type' => 'IN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tambahkan harga baru ke plate_price_histories
        DB::table('plate_price_histories')->insert([
            [
                'plate_variant_id' => $variant->id,
                'old_price' => 0,
                'new_price' => $request->input('price_buy'),
                'type' => 'BUY',
                'user_id' => $user->id, // Added user_id
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plate_variant_id' => $variant->id,
                'old_price' => 0,
                'new_price' => $request->input('price_sell'),
                'type' => 'SELL',
                'user_id' => $user->id, // Added user_id
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        Log::info('Data received from frontend:', $request->all());

        app(MobileNotificationService::class)->notifyLowStockForPlateVariant(
            $variant->fresh(['branch.setting', 'plateType', 'size'])
        );

        return response()->json(['message' => 'Stok baru berhasil ditambahkan', 'data' => $variant->load(['branch','plateType','size'])], 200);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $variant = PlateVariant::find($id);

        if (!$variant) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $variant->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak memiliki akses ke data cabang lain'], 403);
        }

        $validated = $request->validate([
            'plate_type_id' => 'sometimes|exists:plate_types,id',
            'size_id' => 'sometimes|exists:sizes,id',
            'branch_id' => $user->isSuperAdmin() ? 'required|exists:branches,id' : 'nullable',
            'is_active'     => 'sometimes|boolean', // Pastikan divalidasi sebagai boolean
        ]);

        $data = [];
        if (array_key_exists('plate_type_id', $validated)) $data['plate_type_id'] = $validated['plate_type_id'];
        if (array_key_exists('size_id', $validated)) $data['size_id'] = $validated['size_id'];
        
        // Gunakan boolean() agar sinkron dengan tipe data di DB
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if ($user->isSuperAdmin() && array_key_exists('branch_id', $validated)) {
            $data['branch_id'] = $validated['branch_id'];
        } else {
            $data['branch_id'] = $variant->branch_id;
        }

        $variant->update($data);

        // Ambil total qty lama dari stock_movements
        $totalQty = DB::table('stock_movements')
            ->where('plate_variant_id', $id)
            ->sum('qty');

        // Hitung selisih qty
        $qtyDifference = $request->input('qty') - $totalQty;
        if ($qtyDifference !== 0) {
            DB::table('stock_movements')->insert([
                'plate_variant_id' => $id,
                'qty' => $qtyDifference,
                'user_id' => $user->id,
                'type' => 'ADJUSTMENT',
                'description' => 'STOCK OPNAME',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ambil harga terakhir dari plate_price_histories
        $lastPrices = DB::table('plate_price_histories')
            ->where('plate_variant_id', $id)
            ->orderBy('id', 'desc')
            ->limit(2)
            ->get();

        $lastBuyPrice = $lastPrices->where('type', 'BUY')->first()->new_price ?? 0;
        $lastSellPrice = $lastPrices->where('type', 'SELL')->first()->new_price ?? 0;

        // Cek perubahan harga
        if ($request->input('price_buy') != $lastBuyPrice) {
            DB::table('plate_price_histories')->insert([
                'plate_variant_id' => $id,
                'old_price' => $lastBuyPrice,
                'new_price' => $request->input('price_buy'),
                'type' => 'BUY',
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($request->input('price_sell') != $lastSellPrice) {
            DB::table('plate_price_histories')->insert([
                'plate_variant_id' => $id,
                'old_price' => $lastSellPrice,
                'new_price' => $request->input('price_sell'),
                'type' => 'SELL',
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($qtyDifference !== 0) {
            app(MobileNotificationService::class)->notifyLowStockForPlateVariant(
                $variant->fresh(['branch.setting', 'plateType', 'size'])
            );
        }

        return response()->json(['message' => 'Plate variant berhasil diupdate', 'data' => $variant->load(['branch','plateType','size'])]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $variant = PlateVariant::find($id);

        if (!$variant) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $variant->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden: Anda tidak bisa menghapus data cabang lain'], 403);
        }

        $variant->delete();

        return response()->json(['message' => 'Plate variant berhasil dihapus']);
    }
}
