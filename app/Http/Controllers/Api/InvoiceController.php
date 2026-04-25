<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BranchInvoiceCounter;
use App\Models\Customer;
use App\Models\Component;
use App\Models\ComponentStockMovement;
use App\Models\CostType;
use App\Models\CuttingPrice;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemFile;
use App\Models\Machine;
use App\Models\MachineOrder;
use App\Models\Payment;
use App\Models\PlateVariant;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Services\MobileNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function masterData(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isSuperAdmin()
            ? ($request->filled('branch_id') ? (int) $request->input('branch_id') : null)
            : (int) $user->branch_id;

        if ($user->isSuperAdmin() && !$branchId) {
            return response()->json([
                'customers' => [],
                'machines' => [],
                'plate_variants' => [],
                'components' => [],
                'cost_types' => $this->invoiceCostTypes(),
                'cutting_prices' => $this->invoiceCuttingPrices(),
                'machine_orders' => [],
                'service_orders' => [],
            ]);
        }

        return response()->json([
            'customers' => $this->invoiceCustomers($branchId),
            'machines' => $this->invoiceMachines($branchId),
            'plate_variants' => $this->invoicePlateVariants($branchId),
            'components' => $this->invoiceComponents($branchId),
            'cost_types' => $this->invoiceCostTypes(),
            'cutting_prices' => $this->invoiceCuttingPrices(),
            'machine_orders' => $this->invoiceMachineOrders($branchId),
            'service_orders' => $this->invoiceServiceOrders($branchId),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Invoice::query()
            ->with(['branch.setting', 'branch.bankAccounts', 'customer', 'machine.type', 'user', 'items', 'payments'])
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('branches', 'branches.id', '=', 'invoices.branch_id')
            ->leftJoin('machines', 'machines.id', '=', 'invoices.machine_id')
            ->select('invoices.*');

        if (!$user->isSuperAdmin()) {
            $query->where('invoices.branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('invoices.branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('invoices.invoice_number', 'like', '%' . $search . '%')
                    ->orWhere('customers.full_name', 'like', '%' . $search . '%')
                    ->orWhere('branches.name', 'like', '%' . $search . '%');
            });
        } else {
            if ($request->filled('number')) {
                $query->where('invoices.invoice_number', 'like', '%' . $request->input('number') . '%');
            }

            if ($request->filled('customer')) {
                $search = $request->input('customer');
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('customers.full_name', 'like', '%' . $search . '%')
                        ->orWhere('branches.name', 'like', '%' . $search . '%');
                });
            }
        }

        if ($request->filled('machine')) {
            $query->where('machines.machine_number', 'like', '%' . $request->input('machine') . '%');
        }

        if ($request->filled('status')) {
            $status = strtolower($request->input('status'));
            $query->whereRaw('LOWER(invoices.status) LIKE ?', ['%' . $status . '%']);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('invoices.transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoices.transaction_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('production_status')) {
            $productionStatus = $this->normalizeStatusKey($request->input('production_status'));

            $rawStatuses = match ($productionStatus) {
                'pending' => ['Pending'],
                'in-process' => ['In-process', 'Diproses'],
                'completed' => ['Completed'],
                'cancelled' => ['Cancel'],
                default => [ucwords(str_replace('-', ' ', $productionStatus))],
            };

            $query->whereIn('invoices.status', $rawStatuses);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowedSorts = ['id', 'number', 'customer', 'machine', 'grand_total', 'status', 'production_status', 'created_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        if ($sortBy === 'number') {
            $query->orderBy('invoices.invoice_number', $sortDir);
        } elseif ($sortBy === 'customer') {
            $query->orderBy('customers.full_name', $sortDir);
        } elseif ($sortBy === 'machine') {
            $query->orderBy('machines.machine_number', $sortDir);
        } elseif ($sortBy === 'production_status') {
            $query->orderBy('invoices.status', $sortDir);
        } else {
            $query->orderBy('invoices.' . $sortBy, $sortDir);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $result = $query->paginate($perPage);

        $result->getCollection()->transform(fn (Invoice $invoice) => $this->transformListItem($invoice));

        return response()->json($result);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $invoice = Invoice::with([
            'branch.setting',
            'branch.bankAccounts',
            'customer',
            'machine.type',
            'user',
            'items.files',
            'items.plateVariant.plateType',
            'items.plateVariant.size',
            'items.cuttingPrice.machineType',
            'items.cuttingPrice.plateType',
            'items.cuttingPrice.size',
            'items.component.componentCategory',
            'items.component.supplier',
            'items.costType',
            'payments.bankAccount',
            'payments.user',
            'payments.files',
        ])->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $invoice->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->transformDetail($invoice));
    }

    public function uploadItemFiles(Request $request, $id)
    {
        $user = $request->user();
        $invoice = Invoice::with('items')->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $invoice->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'invoice_item_id' => ['required', 'integer', 'exists:invoice_items,id'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:20480', 'mimes:pdf,dxf,dwg,ai,cdr,svg,png,jpg,jpeg'],
        ]);

        $invoiceItem = $invoice->items->firstWhere('id', (int) $validated['invoice_item_id']);

        if (!$invoiceItem) {
            return response()->json(['message' => 'Item invoice tidak valid untuk invoice ini.'], 422);
        }

        if ($invoiceItem->product_type !== 'cutting') {
            return response()->json(['message' => 'Upload file hanya tersedia untuk item cutting.'], 422);
        }

        $storedFiles = [];

        DB::transaction(function () use ($request, $invoice, $invoiceItem, &$storedFiles) {
            foreach ($request->file('files', []) as $file) {
                $storedPath = $file->store("invoice-items/{$invoice->id}/{$invoiceItem->id}", 'public');

                $storedFiles[] = InvoiceItemFile::create([
                    'invoice_item_id' => $invoiceItem->id,
                    'file_path' => $storedPath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_extension' => strtolower((string) $file->getClientOriginalExtension()),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }
        });

        return response()->json([
            'message' => 'File berhasil diunggah.',
            'data' => collect($storedFiles)->map(fn (InvoiceItemFile $file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_extension' => $file->file_extension,
                'file_size' => (int) ($file->file_size ?? 0),
                'mime_type' => $file->mime_type,
            ])->values()->all(),
        ], 201);
    }

    public function downloadItemFile(Request $request, $invoiceId, $fileId)
    {
        $user = $request->user();
        $invoice = Invoice::with('items.files')->find($invoiceId);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $invoice->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = $invoice->items
            ->flatMap(fn (InvoiceItem $item) => $item->files)
            ->firstWhere('id', (int) $fileId);

        if (!$file) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['message' => 'Berkas fisik tidak ditemukan'], 404);
        }

        return Storage::disk('public')->download(
            $file->file_path,
            $file->file_name ?: basename($file->file_path)
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $this->validateInvoicePayload($request, false, null, $user);
        $this->assertPayloadConsistency($validated, $user);

        $invoice = DB::transaction(function () use ($validated, $request, $user) {
            $branchId = $user->isSuperAdmin() ? $validated['branch_id'] : $user->branch_id;
            $itemsPayload = $validated['items'];

            $counterDate = $validated['transaction_date'];
            $invoiceNumber = $this->generateInvoiceNumber($branchId, $counterDate);
            $totals = $this->calculateTotals($itemsPayload, (float) ($validated['discount_pct'] ?? 0));
            $status = $this->determineInitialStatus($itemsPayload);

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => 'sales',
                'source_type' => null,
                'source_id' => null,
                'branch_id' => $branchId,
                'customer_id' => $validated['customer_id'],
                'machine_id' => $validated['machine_id'] ?? null,
                'user_id' => $user->id,
                'transaction_date' => $validated['transaction_date'],
                'status' => $status,
                'total_amount' => $totals['subtotal'],
                'discount_pct' => $totals['discount_pct'],
                'discount_amount' => $totals['discount_amount'],
                'grand_total' => $totals['grand_total'],
            ]);

            $this->syncItems($invoice, $itemsPayload, $user->id);
            $this->createPaymentIfNeeded($invoice, $request, $validated, $branchId);

            return $invoice->fresh([
                'branch.setting',
                'branch.bankAccounts',
                'customer',
                'machine.type',
                'user',
                'items.files',
                'items.plateVariant.plateType',
                'items.plateVariant.size',
                'items.cuttingPrice.machineType',
                'items.cuttingPrice.plateType',
                'items.cuttingPrice.size',
                'items.component.componentCategory',
                'items.component.supplier',
                'items.costType',
                'payments.bankAccount',
                'payments.files',
            ]);
        });

        $notificationService = app(MobileNotificationService::class);
        $notificationService->notifyInvoiceCreated($invoice);
        $invoice->payments->each(fn (Payment $payment) => $notificationService->notifyPaymentCreated($payment));
        $this->notifyLowStockItems($invoice, $notificationService);

        return response()->json([
            'message' => 'Invoice berhasil dibuat',
            'data' => $this->transformDetail($invoice),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => 'Invoice yang sudah dibuat tidak dapat diedit. Silakan cancel pesanan dan buat invoice baru.',
        ], 422);
    }

    public function updateProductionStatus(Request $request, $id)
    {
        $user = $request->user();
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $invoice->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'production_status' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $normalizedStatus = strtolower(trim($validated['production_status']));
        $targetStatus = $this->mapRequestedStatus($normalizedStatus);

        if ($targetStatus === null) {
            return response()->json(['message' => 'Status produksi tidak valid.'], 422);
        }

        $currentStatus = $this->normalizeStatusKey($invoice->status);

        if (!$this->isAllowedProductionTransition($currentStatus, $targetStatus)) {
            return response()->json([
                'message' => 'Perubahan status tidak diizinkan untuk kondisi invoice saat ini.',
            ], 422);
        }

        $updatePayload = [
            'status' => $targetStatus,
        ];

        if ($targetStatus === 'Cancel') {
            $updatePayload['notes'] = filled($validated['notes'] ?? null)
                ? trim($validated['notes'])
                : null;
        }

        $invoice->update($updatePayload);

        return response()->json([
            'message' => 'Status produksi berhasil diperbarui',
            'data' => $this->transformDetail($invoice->fresh([
                'branch.setting',
                'branch.bankAccounts',
                'customer',
                'machine.type',
                'user',
                'items.files',
                'items.plateVariant.plateType',
                'items.plateVariant.size',
                'items.cuttingPrice.machineType',
                'items.cuttingPrice.plateType',
                'items.cuttingPrice.size',
                'items.component.componentCategory',
                'items.component.supplier',
                'items.costType',
                'payments.bankAccount',
                'payments.files',
            ])),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $invoice = Invoice::with('items')->find($id);

        if (!$invoice) {
            return response()->json(['message' => 'Invoice tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $invoice->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($invoice) {
            StockMovement::where('reference_id', (string) $invoice->id)->delete();
            ComponentStockMovement::where('reference_id', (string) $invoice->id)->delete();
            Payment::where('invoice_id', $invoice->id)->delete();
            $invoice->items()->delete();
            $invoice->delete();
        });

        return response()->json(['message' => 'Invoice berhasil dihapus']);
    }

    private function validateInvoicePayload(Request $request, bool $isUpdate, ?Invoice $invoice, $user): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'branch_id' => $user->isSuperAdmin() ? [$required, 'exists:branches,id'] : ['nullable'],
            'customer_id' => [$required, 'exists:customers,id'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'transaction_date' => [$required, 'date'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => [$required, 'array', 'min:1'],
            'items.*.product_type' => [$required, 'in:plate,cutting,component,cost_type'],
            'items.*.plate_variant_id' => ['nullable', 'exists:plate_variants,id'],
            'items.*.cutting_price_id' => ['nullable', 'exists:cutting_prices,id'],
            'items.*.component_id' => ['nullable', 'exists:components,id'],
            'items.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'items.*.pricing_mode' => ['nullable', 'in:easy,medium,difficult,per-minute'],
            'items.*.qty' => [$required, 'integer', 'min:1'],
            'items.*.minutes' => ['nullable', 'integer', 'min:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment.amount' => ['nullable', 'numeric', 'min:0'],
            'payment.payment_method' => ['nullable', 'string', 'max:255'],
            'payment.payment_date' => ['nullable', 'date'],
            'payment.is_dp' => ['nullable', 'boolean'],
            'payment.bank_account_id' => ['nullable', 'exists:branch_bank_accounts,id'],
        ]);
    }

    private function calculateTotals(array $itemsPayload, float $discountPct): array
    {
        $subtotal = 0.0;

        foreach ($itemsPayload as $item) {
            $price = $this->resolveItemPrice($item);
            $qty = (int) $item['qty'];
            $minutes = ($item['product_type'] ?? null) === 'cutting' && ($item['pricing_mode'] ?? null) === 'per-minute'
                ? (int) ($item['minutes'] ?? 0)
                : 0;
            $itemDiscountPct = (float) ($item['discount_pct'] ?? 0);
            $lineBase = $this->calculateLineBase($item['product_type'] ?? null, $item['pricing_mode'] ?? null, $qty, $price, $minutes);
            $lineDiscount = $lineBase * ($itemDiscountPct / 100);
            $subtotal += round($lineBase - $lineDiscount, 2);
        }

        $discountAmount = round($subtotal * ($discountPct / 100), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_pct' => $discountPct,
            'discount_amount' => $discountAmount,
            'grand_total' => round($subtotal - $discountAmount, 2),
        ];
    }

    private function determineInitialStatus(array $itemsPayload): string
    {
        foreach ($itemsPayload as $item) {
            if (($item['product_type'] ?? null) === 'cutting') {
                return 'Pending';
            }
        }

        return 'In-process';
    }

    private function generateInvoiceNumber(int $branchId, string $transactionDate): string
    {
        $date = Carbon::parse($transactionDate);
        $month = (int) $date->format('m');
        $year = (int) $date->format('Y');

        $counter = BranchInvoiceCounter::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['branch_id' => $branchId, 'month' => $month, 'year' => $year],
                ['prefix' => 'INV', 'last_number' => 0]
            );

        $counter->last_number += 1;
        $counter->save();

        return implode('/', [
            $counter->prefix ?: 'INV',
            str_pad((string) $branchId, 2, '0', STR_PAD_LEFT),
            str_pad((string) $counter->last_number, 3, '0', STR_PAD_LEFT),
            str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            (string) $year,
        ]);
    }

    private function syncItems(Invoice $invoice, array $itemsPayload, int $userId): void
    {
        foreach ($itemsPayload as $item) {
            $productType = $item['product_type'];
            $qty = (int) $item['qty'];
            $pricingMode = $item['pricing_mode'] ?? null;
            $minutes = $productType === 'cutting' && $pricingMode === 'per-minute'
                ? (int) ($item['minutes'] ?? 0)
                : 0;
            $price = $this->resolveItemPrice($item);
            $discountPct = (float) ($item['discount_pct'] ?? 0);
            $lineBase = $this->calculateLineBase($productType, $pricingMode, $qty, $price, $minutes);
            $discountAmount = round($lineBase * ($discountPct / 100), 2);
            $subtotal = round($lineBase - $discountAmount, 2);

            $invoiceItem = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_type' => $productType,
                'item_type' => $productType,
                'source_type' => $this->resolveInvoiceItemSourceType($productType),
                'source_id' => $this->resolveInvoiceItemSourceId($productType, $item),
                'description' => $this->buildInvoiceItemDescription($productType, $item),
                'unit' => $this->buildInvoiceItemUnit($productType, $item),
                'plate_variant_id' => $productType === 'plate' ? ($item['plate_variant_id'] ?? null) : null,
                'cutting_price_id' => $productType === 'cutting' ? ($item['cutting_price_id'] ?? null) : null,
                'component_id' => $productType === 'component' ? ($item['component_id'] ?? null) : null,
                'cost_type_id' => $productType === 'cost_type' ? ($item['cost_type_id'] ?? null) : null,
                'pricing_mode' => $productType === 'cutting' ? $pricingMode : null,
                'qty' => $qty,
                'minutes' => $minutes,
                'price' => $price,
                'discount_pct' => $discountPct,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
            ]);

            if ($productType === 'plate' && !empty($item['plate_variant_id'])) {
                $plateVariant = PlateVariant::find($item['plate_variant_id']);
                $availableQty = (int) DB::table('stock_movements')
                    ->where('plate_variant_id', $plateVariant->id)
                    ->sum('qty');

                if ($availableQty < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ['Stok plat tidak mencukupi untuk salah satu item.'],
                    ]);
                }

                StockMovement::create([
                    'plate_variant_id' => $plateVariant->id,
                    'qty' => -1 * $qty,
                    'type' => 'OUT',
                    'description' => 'Invoice ' . $invoice->invoice_number,
                    'reference_id' => (string) $invoice->id,
                    'user_id' => $userId,
                ]);
            }

            if ($productType === 'component' && !empty($item['component_id'])) {
                $component = Component::find($item['component_id']);
                $availableQty = (int) DB::table('component_stock_movements')
                    ->where('component_id', $component->id)
                    ->sum('qty');

                if ($availableQty < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ['Stok component tidak mencukupi untuk salah satu item.'],
                    ]);
                }

                ComponentStockMovement::create([
                    'component_id' => $component->id,
                    'qty' => -1 * $qty,
                    'type' => 'OUT',
                    'description' => 'Invoice ' . $invoice->invoice_number,
                    'reference_id' => (string) $invoice->id,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function createPaymentIfNeeded(Invoice $invoice, Request $request, array $validated, int $branchId): void
    {
        $amount = (float) data_get($validated, 'payment.amount', 0);

        if ($amount <= 0) {
            return;
        }

        Payment::create([
            'invoice_id' => $invoice->id,
            'branch_id' => $branchId,
            'bank_account_id' => data_get($validated, 'payment.bank_account_id'),
            'user_id' => $request->user()?->id,
            'amount' => $amount,
            'payment_method' => data_get($validated, 'payment.payment_method', 'Cash'),
            'payment_type' => (bool) data_get($validated, 'payment.is_dp', $amount < (float) $invoice->grand_total) ? 'cicilan' : 'pelunasan',
            'is_dp' => (bool) data_get($validated, 'payment.is_dp', $amount < (float) $invoice->grand_total),
            'payment_date' => data_get($validated, 'payment.payment_date', $invoice->transaction_date?->format('Y-m-d') ?? now()->toDateString()),
            'proof_image' => $request->input('payment.proof_image'),
        ]);
    }

    private function notifyLowStockItems(Invoice $invoice, MobileNotificationService $notificationService): void
    {
        $invoice->loadMissing(['items.plateVariant.branch.setting', 'items.plateVariant.plateType', 'items.plateVariant.size', 'items.component.branch.setting']);

        foreach ($invoice->items as $item) {
            if ($item->product_type === 'plate' && $item->plateVariant) {
                $notificationService->notifyLowStockForPlateVariant($item->plateVariant);
            }

            if ($item->product_type === 'component' && $item->component) {
                $notificationService->notifyLowStockForComponent($item->component);
            }
        }
    }

    private function resolveItemPrice(array $item): float
    {
        if (($item['product_type'] ?? null) === 'plate') {
            if (isset($item['price'])) {
                return (float) $item['price'];
            }

            $plateVariantId = $item['plate_variant_id'] ?? null;
            $price = DB::table('plate_price_histories')
                ->where('plate_variant_id', $plateVariantId)
                ->where('type', 'SELL')
                ->orderByDesc('id')
                ->value('new_price');

            return (float) ($price ?? 0);
        }

        if (($item['product_type'] ?? null) === 'component') {
            if (isset($item['price'])) {
                return (float) $item['price'];
            }

            $componentId = $item['component_id'] ?? null;
            $price = DB::table('component_price_histories')
                ->where('component_id', $componentId)
                ->where('type', 'SELL')
                ->orderByDesc('id')
                ->value('new_price');

            return (float) ($price ?? 0);
        }

        if (($item['product_type'] ?? null) === 'cost_type') {
            return isset($item['price']) ? (float) $item['price'] : 0;
        }

        if (isset($item['price'])) {
            return (float) $item['price'];
        }

        $cuttingPrice = DB::table('cutting_prices')->find($item['cutting_price_id'] ?? null);

        if (!$cuttingPrice) {
            return 0;
        }

        $pricingMode = $item['pricing_mode'] ?? 'per-minute';

        return match ($pricingMode) {
            'easy' => (float) ($cuttingPrice->price_easy ?? 0),
            'medium' => (float) ($cuttingPrice->price_medium ?? 0),
            'difficult' => (float) ($cuttingPrice->price_difficult ?? 0),
            default => (float) ($cuttingPrice->price_per_minute ?? 0),
        };
    }

    private function calculateLineBase(?string $productType, ?string $pricingMode, int $qty, float $price, int $minutes): float
    {
        if ($productType === 'cutting' && $pricingMode === 'per-minute') {
            return $qty * $price * max($minutes, 0);
        }

        return $qty * $price;
    }

    private function assertPayloadConsistency(array $validated, $user): void
    {
        $branchId = $user->isSuperAdmin()
            ? (int) ($validated['branch_id'] ?? 0)
            : (int) $user->branch_id;

        $customerBranchId = (int) DB::table('customers')
            ->where('id', $validated['customer_id'])
            ->value('branch_id');

        if ($customerBranchId !== $branchId) {
            throw ValidationException::withMessages([
                'customer_id' => ['Pelanggan tidak berada di cabang yang dipilih.'],
            ]);
        }

        if (!empty($validated['machine_id'])) {
            $machineBranchId = (int) DB::table('machines')
                ->where('id', $validated['machine_id'])
                ->value('branch_id');

            if ($machineBranchId !== $branchId) {
                throw ValidationException::withMessages([
                    'machine_id' => ['Mesin tidak berada di cabang yang dipilih.'],
                ]);
            }
        }

        foreach ($validated['items'] as $index => $item) {
            $productType = $item['product_type'];

            if ($productType === 'plate') {
                if (empty($item['plate_variant_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.plate_variant_id" => ['Item plat wajib memilih varian plat.'],
                    ]);
                }

                if (!empty($item['cutting_price_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.cutting_price_id" => ['Item plat tidak boleh memiliki cutting price.'],
                    ]);
                }
            }

            if ($productType === 'component') {
                if (empty($item['component_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.component_id" => ['Item component wajib memilih component.'],
                    ]);
                }

                if (!empty($item['plate_variant_id']) || !empty($item['cutting_price_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.component_id" => ['Item component tidak boleh memiliki plate variant atau cutting price.'],
                    ]);
                }

                $componentBranchId = (int) DB::table('components')
                    ->where('id', $item['component_id'])
                    ->value('branch_id');

                if ($componentBranchId !== $branchId) {
                    throw ValidationException::withMessages([
                        "items.$index.component_id" => ['Component tidak berada di cabang yang dipilih.'],
                    ]);
                }
            }

            if ($productType === 'cost_type') {
                if (empty($item['cost_type_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.cost_type_id" => ['Item biaya wajib memilih tipe biaya.'],
                    ]);
                }

                if (!empty($item['plate_variant_id']) || !empty($item['cutting_price_id']) || !empty($item['component_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.cost_type_id" => ['Item biaya tidak boleh memiliki referensi plat, cutting price, atau component.'],
                    ]);
                }
            }

            if ($productType === 'cutting') {
                if (empty($item['cutting_price_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.cutting_price_id" => ['Item cutting wajib memilih harga cutting.'],
                    ]);
                }

                if (empty($item['pricing_mode'])) {
                    throw ValidationException::withMessages([
                        "items.$index.pricing_mode" => ['Mode harga cutting wajib dipilih.'],
                    ]);
                }

                if (!empty($item['plate_variant_id'])) {
                    throw ValidationException::withMessages([
                        "items.$index.plate_variant_id" => ['Item cutting tidak boleh memiliki plate variant.'],
                    ]);
                }

                if (($item['pricing_mode'] ?? null) === 'per-minute' && empty($item['minutes'])) {
                    throw ValidationException::withMessages([
                        "items.$index.minutes" => ['Jumlah menit wajib diisi untuk mode per-menit.'],
                    ]);
                }
            }
        }

        $paymentMethod = trim((string) data_get($validated, 'payment.payment_method', ''));
        $paymentAmount = (float) data_get($validated, 'payment.amount', 0);

        if ($paymentMethod !== '' && strcasecmp($paymentMethod, 'Pay Later') !== 0 && $paymentAmount <= 0) {
            throw ValidationException::withMessages([
                'payment.amount' => ['Jumlah bayar wajib lebih dari 0 jika metode pembayaran bukan Pay Later.'],
            ]);
        }

        if (strcasecmp($paymentMethod, 'Pay Later') === 0 && $paymentAmount > 0) {
            throw ValidationException::withMessages([
                'payment.amount' => ['Jumlah bayar harus 0 jika metode pembayaran adalah Pay Later.'],
            ]);
        }
    }

    private function invoiceCustomers(?int $branchId): array
    {
        return Customer::query()
            ->select(['id', 'full_name', 'phone_number', 'branch_id'])
            ->with(['branch:id,name'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('full_name')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone_number' => $customer->phone_number,
                'branch' => $customer->branch ? [
                    'id' => $customer->branch->id,
                    'name' => $customer->branch->name,
                ] : null,
            ])
            ->all();
    }

    private function invoiceMachines(?int $branchId): array
    {
        return Machine::query()
            ->select(['machines.id', 'machines.machine_number', 'machines.is_active', 'machines.branch_id', 'machines.machine_type_id'])
            ->with(['type:id,name'])
            ->when($branchId, fn ($query) => $query->where('machines.branch_id', $branchId))
            ->where('machines.is_active', true)
            ->orderBy('machines.machine_number')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->id,
                'machine_number' => $machine->machine_number,
                'is_active' => (bool) $machine->is_active,
                'branch_id' => $machine->branch_id,
                'type' => $machine->type ? [
                    'id' => $machine->type->id,
                    'name' => $machine->type->name,
                ] : null,
            ])
            ->all();
    }

    private function invoicePlateVariants(?int $branchId): array
    {
        return PlateVariant::query()
            ->select('plate_variants.*')
            ->leftJoin('plate_types', 'plate_types.id', '=', 'plate_variants.plate_type_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'plate_variants.size_id')
            ->when($branchId, fn ($query) => $query->where('plate_variants.branch_id', $branchId))
            ->where('plate_variants.is_active', true)
            ->orderBy('plate_types.name')
            ->orderBy('sizes.value')
            ->addSelect(['price_sell' => function ($query) {
                $query->select('new_price')
                    ->from('plate_price_histories')
                    ->whereColumn('plate_price_histories.plate_variant_id', 'plate_variants.id')
                    ->where('type', 'SELL')
                    ->orderByDesc('id')
                    ->limit(1);
            }])
            ->addSelect(['qty' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(qty), 0)'))
                    ->from('stock_movements')
                    ->whereColumn('stock_movements.plate_variant_id', 'plate_variants.id');
            }])
            ->addSelect(['branch_name' => function ($query) {
                $query->select('name')
                    ->from('branches')
                    ->whereColumn('branches.id', 'plate_variants.branch_id')
                    ->limit(1);
            }])
            ->addSelect(['plate_type_name' => function ($query) {
                $query->select('name')
                    ->from('plate_types')
                    ->whereColumn('plate_types.id', 'plate_variants.plate_type_id')
                    ->limit(1);
            }])
            ->addSelect(['size_value' => function ($query) {
                $query->select('value')
                    ->from('sizes')
                    ->whereColumn('sizes.id', 'plate_variants.size_id')
                    ->limit(1);
            }])
            ->get()
            ->toArray();
    }

    private function invoiceComponents(?int $branchId): array
    {
        return Component::query()
            ->select('components.*')
            ->when($branchId, fn ($query) => $query->where('components.branch_id', $branchId))
            ->orderBy('components.name')
            ->orderBy('components.type_size')
            ->addSelect(['price_sell' => function ($query) {
                $query->select('new_price')
                    ->from('component_price_histories')
                    ->whereColumn('component_price_histories.component_id', 'components.id')
                    ->where('type', 'SELL')
                    ->orderByDesc('id')
                    ->limit(1);
            }])
            ->addSelect(['qty' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(qty), 0)'))
                    ->from('component_stock_movements')
                    ->whereColumn('component_stock_movements.component_id', 'components.id');
            }])
            ->addSelect(['branch_name' => function ($query) {
                $query->select('name')
                    ->from('branches')
                    ->whereColumn('branches.id', 'components.branch_id')
                    ->limit(1);
            }])
            ->get()
            ->toArray();
    }

    private function invoiceCostTypes(): array
    {
        return CostType::query()
            ->select(['id', 'name', 'description'])
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function invoiceCuttingPrices(): array
    {
        return CuttingPrice::query()
            ->select('cutting_prices.*')
            ->with([
                'machineType:id,name',
                'plateType:id,name',
                'size:id,value',
            ])
            ->leftJoin('machine_types', 'machine_types.id', '=', 'cutting_prices.machine_type_id')
            ->leftJoin('plate_types', 'plate_types.id', '=', 'cutting_prices.plate_type_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'cutting_prices.size_id')
            ->where('cutting_prices.is_active', true)
            ->whereNull('machine_types.deleted_at')
            ->orderBy('plate_types.name')
            ->orderBy('sizes.value')
            ->orderBy('machine_types.name')
            ->get()
            ->toArray();
    }

    private function invoiceMachineOrders(?int $branchId): array
    {
        return MachineOrder::query()
            ->with([
                'customer:id,full_name',
                'machine:id,machine_number',
                'costs:id,machine_order_id,cost_name_snapshot,description,qty,price,total',
            ])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['ready', 'in_shipping', 'accepted', 'completed'])
            ->whereDoesntHave('invoices')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (MachineOrder $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_date' => optional($order->order_date)->format('Y-m-d'),
                    'status' => $order->status,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer?->full_name,
                    'machine_id' => $order->machine_id,
                    'machine_name' => $order->machine_name_snapshot ?: $order->machine?->machine_number,
                    'qty' => (int) $order->qty,
                    'base_price' => (float) $order->base_price,
                    'subtotal' => (float) $order->subtotal,
                    'additional_cost_total' => (float) $order->additional_cost_total,
                    'grand_total' => (float) $order->grand_total,
                    'paid_total' => (float) $order->paid_total,
                    'remaining_total' => (float) $order->remaining_total,
                    'notes' => $order->notes,
                    'costs' => $order->costs->map(fn ($cost) => [
                        'id' => $cost->id,
                        'cost_name' => $cost->cost_name_snapshot,
                        'description' => $cost->description,
                        'qty' => (float) $cost->qty,
                        'price' => (float) $cost->price,
                        'total' => (float) $cost->total,
                    ])->values()->all(),
                ];
            })
            ->all();
    }

    private function invoiceServiceOrders(?int $branchId): array
    {
        return ServiceOrder::query()
            ->with([
                'customer:id,full_name',
                'components:id,service_order_id,component_id,component_name_snapshot,qty,notes,billable',
                'components.component:id,name',
            ])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->whereNull('invoice_id')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (ServiceOrder $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_type' => $order->order_type,
                    'order_date' => optional($order->order_date)->format('Y-m-d'),
                    'status' => $order->status,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer?->full_name,
                    'title' => $order->title,
                    'category' => $order->category,
                    'location' => $order->location,
                    'planned_start_date' => optional($order->planned_start_date)->format('Y-m-d'),
                    'duration_days' => $order->duration_days,
                    'components' => $order->components->map(fn ($component) => [
                        'id' => $component->id,
                        'component_id' => $component->component_id,
                        'component_name' => $component->component_name_snapshot ?: $component->component?->name,
                        'qty' => (int) $component->qty,
                        'notes' => $component->notes,
                        'billable' => (bool) $component->billable,
                    ])->values()->all(),
                ];
            })
            ->all();
    }

    private function transformListItem(Invoice $invoice): array
    {
        $paid = (float) $invoice->payments->sum('amount');
        $grandTotal = (float) $invoice->grand_total;
        $remaining = max($grandTotal - $paid, 0);
        $productionStatus = $this->normalizeStatusKey($invoice->status);
        $paymentStatus = $this->resolvePaymentStatus($paid, $grandTotal);
        $overdue = $this->resolveOverdueMeta($invoice, $paymentStatus, $productionStatus);

        return [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'invoice_type' => $invoice->invoice_type ?? 'sales',
            'source_type' => $invoice->source_type,
            'source_id' => $invoice->source_id,
            'customer' => $invoice->customer?->full_name,
            'branch' => $invoice->branch?->name,
            'machine' => $invoice->machine?->machine_number ?: '-',
            'notes' => $invoice->notes,
            'grand_total' => $grandTotal,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $paid >= $grandTotal && $grandTotal > 0 ? 'lunas' : ($paid > 0 ? 'dp' : 'belum'),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->formatPaymentStatusLabel($paymentStatus),
            'is_overdue' => $overdue['is_overdue'],
            'overdue_days' => $overdue['overdue_days'],
            'overdue_label' => $overdue['label'],
            'production_status' => $productionStatus,
            'production_status_label' => $this->formatStatusLabel($productionStatus),
            'date' => optional($invoice->transaction_date)->format('Y-m-d'),
        ];
    }

    private function transformDetail(Invoice $invoice): array
    {
        $paid = (float) $invoice->payments->sum('amount');
        $grandTotal = (float) $invoice->grand_total;
        $remaining = max($grandTotal - $paid, 0);
        $productionStatus = $this->normalizeStatusKey($invoice->status);
        $paymentStatus = $this->resolvePaymentStatus($paid, $grandTotal);
        $overdue = $this->resolveOverdueMeta($invoice, $paymentStatus, $productionStatus);

        return [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'invoice_type' => $invoice->invoice_type ?? 'sales',
            'source_type' => $invoice->source_type,
            'source_id' => $invoice->source_id,
            'date' => optional($invoice->transaction_date)->format('Y-m-d'),
            'customer' => $invoice->customer?->full_name,
            'customer_id' => $invoice->customer_id,
            'branch' => $invoice->branch?->name,
            'branch_id' => $invoice->branch_id,
            'machine' => $invoice->machine?->machine_number ?: '-',
            'machine_id' => $invoice->machine_id,
            'petugas' => $invoice->user?->name,
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'payment_status' => $paymentStatus,
            'payment_status_label' => $this->formatPaymentStatusLabel($paymentStatus),
            'production_status' => $productionStatus,
            'production_status_label' => $this->formatStatusLabel($productionStatus),
            'is_overdue' => $overdue['is_overdue'],
            'overdue_days' => $overdue['overdue_days'],
            'overdue_label' => $overdue['label'],
            'subtotal' => (float) $invoice->total_amount,
            'discount_pct' => (float) $invoice->discount_pct,
            'discount_amount' => (float) $invoice->discount_amount,
            'grand_total' => $grandTotal,
            'paid' => $paid,
            'remaining' => $remaining,
            'branch_detail' => [
                'name' => $invoice->branch?->name,
                'city' => $invoice->branch?->city,
                'address' => $invoice->branch?->address,
                'phone' => $invoice->branch?->phone,
                'email' => $invoice->branch?->email,
                'website' => $invoice->branch?->website,
            ],
            'branch_setting' => [
                'invoice_header_name' => $invoice->branch?->setting?->invoice_header_name,
                'invoice_header_position' => $invoice->branch?->setting?->invoice_header_position,
                'invoice_footer_note' => $invoice->branch?->setting?->invoice_footer_note,
            ],
            'bank_accounts' => $invoice->branch?->bankAccounts?->map(fn ($account) => [
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_holder' => $account->account_holder,
                'bank_code' => $account->bank_code,
                'is_default' => (bool) $account->is_default,
            ])->values()->all() ?? [],
            'payments' => $invoice->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'payment_type' => $payment->payment_type ?: ($payment->is_dp ? 'cicilan' : 'pelunasan'),
                'is_dp' => (bool) $payment->is_dp,
                'date' => optional($payment->payment_date)->format('Y-m-d'),
                'user_id' => $payment->user_id,
                'handled_by' => $payment->user?->name,
                'note' => $payment->note ?: $payment->bankAccount?->bank_name,
                'files' => $payment->files->map(fn (\App\Models\PaymentFile $file) => [
                    'id' => $file->id,
                    'file_name' => $file->file_name,
                    'file_extension' => $file->file_extension,
                    'file_size' => (int) ($file->file_size ?? 0),
                    'mime_type' => $file->mime_type,
                ])->values()->all(),
            ])->values()->all(),
            'source_detail' => $this->resolveInvoiceSourceDetail($invoice),
            'items' => $invoice->items->map(function (InvoiceItem $item) {
                $isPlate = $item->product_type === 'plate';
                $isComponent = $item->product_type === 'component';
                $isCostType = $item->product_type === 'cost_type';
                $isLegacyCutting = $item->product_type === 'cutting';
                $descriptor = $item->description ?: ($isPlate
                    ? trim(($item->plateVariant?->plateType?->name ?? 'Plat') . ' ' . ($item->plateVariant?->size?->value ?? ''))
                    : ($isComponent
                        ? trim(($item->component?->name ?? 'Component') . ' ' . ($item->component?->type_size ?? ''))
                        : ($isCostType
                            ? trim($item->costType?->name ?? 'Biaya')
                            : trim(($item->cuttingPrice?->machineType?->name ?? 'Cutting') . ' / ' . ($item->cuttingPrice?->plateType?->name ?? '') . ' / ' . ($item->cuttingPrice?->size?->value ?? '')))));

                $typeLabel = match (true) {
                    $isPlate => 'Plat',
                    $isComponent => 'Component',
                    $isCostType => 'Biaya',
                    $isLegacyCutting => 'Cutting',
                    filled($item->item_type) => ucwords(str_replace(['_', '-'], ' ', (string) $item->item_type)),
                    default => 'Item',
                };

                return [
                    'id' => $item->id,
                    'type' => $typeLabel,
                    'product_type' => $item->product_type,
                    'item_type' => $item->item_type,
                    'source_type' => $item->source_type,
                    'source_id' => $item->source_id,
                    'unit' => $item->unit,
                    'desc' => !$item->description && !$isPlate && !$isComponent && !$isCostType && $isLegacyCutting
                        ? trim($descriptor . ' / ' . $this->formatPricingMode($item->pricing_mode))
                        : $descriptor,
                    'qty' => $item->qty,
                    'minutes' => $item->minutes,
                    'price' => (float) $item->price,
                    'discount' => (float) $item->discount_pct,
                    'subtotal' => (float) $item->subtotal,
                    'component_id' => $item->component_id,
                    'cost_type_id' => $item->cost_type_id,
                    'plate_variant_id' => $item->plate_variant_id,
                    'cutting_price_id' => $item->cutting_price_id,
                    'pricing_mode' => $item->pricing_mode,
                    'files' => $item->files->map(fn (InvoiceItemFile $file) => [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'file_extension' => $file->file_extension,
                        'file_size' => (int) ($file->file_size ?? 0),
                        'mime_type' => $file->mime_type,
                        'uploaded_at' => optional($file->created_at)->format('Y-m-d H:i:s'),
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    private function resolveInvoiceSourceDetail(Invoice $invoice): ?array
    {
        if ($invoice->source_type === 'machine_order' && $invoice->source_id) {
            $order = MachineOrder::query()
                ->with([
                    'customer:id,full_name',
                    'machine:id,machine_number',
                    'sales:id,name',
                    'assignments.user:id,name',
                    'components.component:id,name,type_size',
                    'costs:id,machine_order_id,cost_name_snapshot,description,qty,price,total',
                ])
                ->find($invoice->source_id);

            if (!$order) {
                return null;
            }

            return [
                'type' => 'machine_order',
                'order_number' => $order->order_number,
                'order_date' => optional($order->order_date)->format('Y-m-d'),
                'status' => $order->status,
                'status_label' => $this->formatStatusLabel($this->normalizeStatusKey((string) $order->status)),
                'customer_name' => $order->customer?->full_name,
                'machine_name' => $order->machine?->machine_number ?: $order->machine_name_snapshot,
                'sales_name' => $order->sales?->name,
                'qty' => (int) ($order->qty ?? 0),
                'estimated_start_date' => optional($order->estimated_start_date)->format('Y-m-d'),
                'estimated_finish_date' => optional($order->estimated_finish_date)->format('Y-m-d'),
                'actual_finish_date' => optional($order->actual_finish_date)->format('Y-m-d'),
                'notes' => $order->notes,
                'internal_notes' => $order->internal_notes,
                'subtotal' => (float) $order->subtotal,
                'additional_cost_total' => (float) $order->additional_cost_total,
                'grand_total' => (float) $order->grand_total,
                'assignments' => $order->assignments->map(fn ($assignment) => [
                    'user_name' => $assignment->user?->name,
                    'role' => $assignment->role,
                    'notes' => $assignment->notes,
                ])->values()->all(),
                'components' => $order->components->map(fn ($component) => [
                    'name' => $component->component?->name ?: $component->component_name_snapshot,
                    'type_size' => $component->component?->type_size,
                    'qty' => (int) ($component->qty ?? 0),
                    'notes' => $component->notes,
                ])->values()->all(),
                'costs' => $order->costs->map(fn ($cost) => [
                    'name' => $cost->cost_name_snapshot,
                    'description' => $cost->description,
                    'qty' => (float) ($cost->qty ?? 0),
                    'price' => (float) ($cost->price ?? 0),
                    'total' => (float) ($cost->total ?? 0),
                ])->values()->all(),
            ];
        }

        if ($invoice->source_type === 'service_order' && $invoice->source_id) {
            $order = ServiceOrder::query()
                ->with([
                    'customer:id,full_name',
                    'assignments.user:id,name',
                    'components.component:id,name,type_size',
                ])
                ->find($invoice->source_id);

            if (!$order) {
                return null;
            }

            return [
                'type' => 'service_order',
                'order_number' => $order->order_number,
                'order_type' => $order->order_type,
                'order_type_label' => $order->order_type === 'training' ? 'Pelatihan' : 'Servis',
                'order_date' => optional($order->order_date)->format('Y-m-d'),
                'status' => $order->status,
                'status_label' => $this->formatStatusLabel($this->normalizeStatusKey((string) $order->status)),
                'customer_name' => $order->customer?->full_name,
                'title' => $order->title,
                'category' => $order->category,
                'description' => $order->description,
                'location' => $order->location,
                'planned_start_date' => optional($order->planned_start_date)->format('Y-m-d'),
                'duration_days' => (int) ($order->duration_days ?? 0),
                'actual_start_date' => optional($order->actual_start_date)->format('Y-m-d'),
                'actual_finish_date' => optional($order->actual_finish_date)->format('Y-m-d'),
                'completion_notes' => $order->completion_notes,
                'notes' => $order->notes,
                'assignments' => $order->assignments->map(fn ($assignment) => [
                    'user_name' => $assignment->user?->name,
                    'role' => $assignment->role,
                    'notes' => $assignment->notes,
                ])->values()->all(),
                'components' => $order->components->map(fn ($component) => [
                    'name' => $component->component?->name ?: $component->component_name_snapshot,
                    'type_size' => $component->component?->type_size,
                    'qty' => (int) ($component->qty ?? 0),
                    'notes' => $component->notes,
                    'billable' => (bool) $component->billable,
                ])->values()->all(),
            ];
        }

        return null;
    }

    private function resolveInvoiceItemSourceType(string $productType): ?string
    {
        return match ($productType) {
            'plate' => 'plate_variant',
            'cutting' => 'cutting_price',
            'component' => 'component',
            'cost_type' => 'cost_type',
            default => null,
        };
    }

    private function resolveInvoiceItemSourceId(string $productType, array $item): ?int
    {
        return match ($productType) {
            'plate' => isset($item['plate_variant_id']) ? (int) $item['plate_variant_id'] : null,
            'cutting' => isset($item['cutting_price_id']) ? (int) $item['cutting_price_id'] : null,
            'component' => isset($item['component_id']) ? (int) $item['component_id'] : null,
            'cost_type' => isset($item['cost_type_id']) ? (int) $item['cost_type_id'] : null,
            default => null,
        };
    }

    private function buildInvoiceItemDescription(string $productType, array $item): ?string
    {
        return match ($productType) {
            'plate' => $this->buildPlateInvoiceItemDescription($item),
            'cutting' => $this->buildCuttingInvoiceItemDescription($item),
            'component' => $this->buildComponentInvoiceItemDescription($item),
            'cost_type' => $this->buildCostTypeInvoiceItemDescription($item),
            default => null,
        };
    }

    private function buildInvoiceItemUnit(string $productType, array $item): ?string
    {
        if ($productType === 'cutting' && ($item['pricing_mode'] ?? null) === 'per-minute') {
            return 'menit';
        }

        return match ($productType) {
            'plate' => 'lembar',
            'cutting', 'component', 'cost_type' => 'pcs',
            default => null,
        };
    }

    private function buildPlateInvoiceItemDescription(array $item): ?string
    {
        if (empty($item['plate_variant_id'])) {
            return null;
        }

        $plateVariant = PlateVariant::with(['plateType:id,name', 'size:id,value'])->find($item['plate_variant_id']);

        if (!$plateVariant) {
            return null;
        }

        return trim(($plateVariant->plateType?->name ?? 'Plat') . ' ' . ($plateVariant->size?->value ?? ''));
    }

    private function buildCuttingInvoiceItemDescription(array $item): ?string
    {
        if (empty($item['cutting_price_id'])) {
            return null;
        }

        $cuttingPrice = CuttingPrice::with(['machineType:id,name', 'plateType:id,name', 'size:id,value'])
            ->find($item['cutting_price_id']);

        if (!$cuttingPrice) {
            return null;
        }

        $parts = array_filter([
            $cuttingPrice->machineType?->name ?: 'Cutting',
            $cuttingPrice->plateType?->name,
            $cuttingPrice->size?->value,
            $this->formatPricingMode($item['pricing_mode'] ?? null),
        ]);

        return implode(' / ', $parts);
    }

    private function buildComponentInvoiceItemDescription(array $item): ?string
    {
        if (empty($item['component_id'])) {
            return null;
        }

        $component = Component::find($item['component_id']);

        if (!$component) {
            return null;
        }

        return trim(($component->name ?? 'Component') . ' ' . ($component->type_size ?? ''));
    }

    private function buildCostTypeInvoiceItemDescription(array $item): ?string
    {
        if (empty($item['cost_type_id'])) {
            return null;
        }

        return CostType::find($item['cost_type_id'])?->name;
    }

    private function resolvePaymentStatus(float $paid, float $grandTotal): string
    {
        if ($grandTotal > 0 && $paid >= $grandTotal) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }

    private function formatPaymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Paid',
            'partially_paid' => 'Partially Paid',
            default => 'Unpaid',
        };
    }

    private function resolveOverdueMeta(Invoice $invoice, string $paymentStatus, string $productionStatus): array
    {
        if ($paymentStatus === 'paid' || $productionStatus !== 'completed' || !$invoice->transaction_date) {
            return [
                'is_overdue' => false,
                'overdue_days' => 0,
                'label' => null,
            ];
        }

        $overdueDays = Carbon::parse($invoice->transaction_date)->startOfDay()->diffInDays(now()->startOfDay(), false);

        if ($overdueDays <= 0) {
            return [
                'is_overdue' => false,
                'overdue_days' => 0,
                'label' => null,
            ];
        }

        return [
            'is_overdue' => true,
            'overdue_days' => $overdueDays,
            'label' => 'Overdue ' . $overdueDays . ' hari',
        ];
    }

    private function formatPricingMode(?string $pricingMode): string
    {
        return match ($pricingMode) {
            'easy' => 'Easy',
            'medium' => 'Medium',
            'difficult' => 'Difficult',
            'per-minute' => 'Per Menit',
            default => '',
        };
    }

    private function normalizeStatusKey(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'pending' => 'pending',
            'in-process', 'in process', 'in-progress', 'in progress', 'diproses', 'process' => 'in-process',
            'completed', 'selesai' => 'completed',
            'cancel', 'cancelled', 'canceled' => 'cancelled',
            default => strtolower(trim((string) $status)),
        };
    }

    private function formatStatusLabel(?string $status): string
    {
        return match ($this->normalizeStatusKey($status)) {
            'pending' => 'Pending',
            'in-process' => 'In-progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('-', ' ', (string) $status)),
        };
    }

    private function mapRequestedStatus(string $normalizedStatus): ?string
    {
        return match ($normalizedStatus) {
            'pending' => 'Pending',
            'in-process', 'in process', 'in-progress', 'in progress', 'process', 'diproses' => 'In-process',
            'completed', 'selesai' => 'Completed',
            'cancel', 'cancelled', 'canceled' => 'Cancel',
            default => null,
        };
    }

    private function isAllowedProductionTransition(string $currentStatus, string $targetStatus): bool
    {
        $targetKey = $this->normalizeStatusKey($targetStatus);

        return match ($currentStatus) {
            'pending' => in_array($targetKey, ['in-process', 'cancelled'], true),
            'in-process' => in_array($targetKey, ['completed', 'cancelled'], true),
            default => false,
        };
    }
}


