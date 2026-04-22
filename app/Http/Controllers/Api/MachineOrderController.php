<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\CostType;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\MachineOrder;
use App\Models\MachineOrderComponent;
use App\Models\MachineOrderCost;
use App\Models\MachineOrderPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MachineOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = MachineOrder::query()
            ->with([
                'branch',
                'customer',
                'sales',
                'machine.type',
                'creator',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'machine_orders.customer_id')
            ->leftJoin('machines', 'machines.id', '=', 'machine_orders.machine_id')
            ->select('machine_orders.*');

        if (!$user->isSuperAdmin()) {
            $query->where('machine_orders.branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('machine_orders.branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('machine_orders.customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('machine_id')) {
            $query->where('machine_orders.machine_id', $request->integer('machine_id'));
        }

        if ($request->filled('status')) {
            $query->where('machine_orders.status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('machine_orders.order_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('machine_orders.order_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('machine_orders.order_number', 'like', "%{$search}%")
                    ->orWhere('machine_orders.machine_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('customers.full_name', 'like', "%{$search}%")
                    ->orWhere('machines.machine_number', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowedSorts = ['id', 'order_number', 'order_date', 'status', 'grand_total', 'created_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query->orderBy('machine_orders.' . $sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json(
            $query->paginate($perPage)->through(fn (MachineOrder $order) => $this->transformListItem($order))
        );
    }

    public function show(Request $request, $id)
    {
        $order = $this->findAccessibleOrder($request, $id);

        return response()->json($this->transformDetail($order));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $this->validatePayload($request, $user, false, null);

        $order = DB::transaction(function () use ($validated, $user) {
            $branchId = $user->isSuperAdmin()
                ? (int) $validated['branch_id']
                : (int) $user->branch_id;

            $machine = Machine::with(['machineComponents.component'])->findOrFail($validated['machine_id']);
            $this->assertBranchConsistency($branchId, $validated, $machine);

            $orderNumber = $this->generateOrderNumber($branchId, $validated['order_date']);
            $basePrice = isset($validated['base_price']) ? (float) $validated['base_price'] : (float) $machine->base_price;
            $qty = (int) ($validated['qty'] ?? 1);

            $order = MachineOrder::create([
                'branch_id' => $branchId,
                'order_number' => $orderNumber,
                'order_date' => $validated['order_date'],
                'customer_id' => $validated['customer_id'],
                'sales_id' => $validated['sales_id'] ?? null,
                'machine_id' => $machine->id,
                'qty' => $qty,
                'machine_name_snapshot' => $machine->machine_number,
                'base_price' => $basePrice,
                'discount_type' => $validated['discount_type'] ?? null,
                'discount_value' => (float) ($validated['discount_value'] ?? 0),
                'estimated_start_date' => $validated['estimated_start_date'] ?? null,
                'estimated_finish_date' => $validated['estimated_finish_date'] ?? null,
                'actual_finish_date' => $validated['actual_finish_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncCosts($order, $validated['costs'] ?? []);
            $this->syncComponents($order, $validated['components'] ?? null, $machine, $qty);
            $this->syncPayments($order, $validated['payments'] ?? [], $user->id);
            $this->refreshTotals($order);

            return $order->fresh($this->detailRelations());
        });

        return response()->json([
            'message' => 'Machine order berhasil dibuat.',
            'data' => $this->transformDetail($order),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $order = $this->findAccessibleOrder($request, $id);
        $validated = $this->validatePayload($request, $user, true, $order);

        $order = DB::transaction(function () use ($validated, $user, $order) {
            $branchId = $user->isSuperAdmin()
                ? (int) ($validated['branch_id'] ?? $order->branch_id)
                : (int) $user->branch_id;

            $machineId = (int) ($validated['machine_id'] ?? $order->machine_id);
            $machine = Machine::with(['machineComponents.component'])->findOrFail($machineId);
            $consistencyPayload = array_merge([
                'customer_id' => $order->customer_id,
                'sales_id' => $order->sales_id,
            ], $validated);
            $this->assertBranchConsistency($branchId, $consistencyPayload, $machine);

            $basePrice = isset($validated['base_price']) ? (float) $validated['base_price'] : (float) $machine->base_price;
            $qty = (int) ($validated['qty'] ?? $order->qty ?? 1);

            $order->update([
                'branch_id' => $branchId,
                'order_date' => $validated['order_date'] ?? $order->order_date,
                'customer_id' => $validated['customer_id'] ?? $order->customer_id,
                'sales_id' => array_key_exists('sales_id', $validated) ? ($validated['sales_id'] ?? null) : $order->sales_id,
                'machine_id' => $machine->id,
                'qty' => $qty,
                'machine_name_snapshot' => $machine->machine_number,
                'base_price' => $basePrice,
                'discount_type' => array_key_exists('discount_type', $validated) ? ($validated['discount_type'] ?? null) : $order->discount_type,
                'discount_value' => array_key_exists('discount_value', $validated) ? (float) $validated['discount_value'] : $order->discount_value,
                'estimated_start_date' => array_key_exists('estimated_start_date', $validated) ? ($validated['estimated_start_date'] ?? null) : $order->estimated_start_date,
                'estimated_finish_date' => array_key_exists('estimated_finish_date', $validated) ? ($validated['estimated_finish_date'] ?? null) : $order->estimated_finish_date,
                'actual_finish_date' => array_key_exists('actual_finish_date', $validated) ? ($validated['actual_finish_date'] ?? null) : $order->actual_finish_date,
                'notes' => array_key_exists('notes', $validated) ? ($validated['notes'] ?? null) : $order->notes,
                'internal_notes' => array_key_exists('internal_notes', $validated) ? ($validated['internal_notes'] ?? null) : $order->internal_notes,
                'status' => $validated['status'] ?? $order->status,
                'updated_by' => $user->id,
            ]);

            $this->syncCosts($order, $validated['costs'] ?? []);
            $this->syncComponents($order, $validated['components'] ?? null, $machine, $qty);

            if (array_key_exists('payments', $validated)) {
                $this->syncPayments($order, $validated['payments'] ?? [], $user->id);
            }

            $this->refreshTotals($order);

            return $order->fresh($this->detailRelations());
        });

        return response()->json([
            'message' => 'Machine order berhasil diperbarui.',
            'data' => $this->transformDetail($order),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $order = $this->findAccessibleOrder($request, $id);
        $order->delete();

        return response()->json([
            'message' => 'Machine order berhasil dihapus.',
        ]);
    }

    private function validatePayload(Request $request, $user, bool $isUpdate, ?MachineOrder $order): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'branch_id' => $user->isSuperAdmin() ? [$required, 'exists:branches,id'] : ['nullable'],
            'order_date' => [$required, 'date'],
            'customer_id' => [$required, 'exists:customers,id'],
            'sales_id' => ['nullable', 'exists:users,id'],
            'machine_id' => [$required, 'exists:machines,id'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:percent,amount'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_start_date' => ['nullable', 'date'],
            'estimated_finish_date' => ['nullable', 'date'],
            'actual_finish_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,confirmed,in_production,ready,completed,cancelled'],
            'costs' => ['nullable', 'array'],
            'costs.*.cost_type_id' => ['nullable', 'exists:cost_types,id'],
            'costs.*.cost_name' => ['nullable', 'string', 'max:255'],
            'costs.*.description' => ['nullable', 'string'],
            'costs.*.qty' => ['nullable', 'numeric', 'min:0.01'],
            'costs.*.price' => ['required_with:costs', 'numeric', 'min:0'],
            'components' => ['nullable', 'array'],
            'components.*.component_id' => ['nullable', 'exists:components,id'],
            'components.*.component_name' => ['nullable', 'string', 'max:255'],
            'components.*.qty' => ['required_with:components', 'integer', 'min:1'],
            'components.*.notes' => ['nullable', 'string'],
            'components.*.is_optional' => ['nullable', 'boolean'],
            'payments' => ['sometimes', 'array'],
            'payments.*.payment_date' => ['required_with:payments', 'date'],
            'payments.*.payment_type' => ['required_with:payments', 'in:dp,pelunasan,cicilan,refund'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.payment_method' => ['nullable', 'string', 'max:255'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string'],
            'payments.*.received_by' => ['nullable', 'exists:users,id'],
        ]);
    }

    private function assertBranchConsistency(int $branchId, array $validated, Machine $machine): void
    {
        $customer = Customer::findOrFail($validated['customer_id']);

        if ((int) $customer->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'customer_id' => ['Pelanggan tidak berada di cabang yang dipilih.'],
            ]);
        }

        if ((int) $machine->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'machine_id' => ['Mesin tidak berada di cabang yang dipilih.'],
            ]);
        }

        if (!empty($validated['sales_id'])) {
            $sales = User::findOrFail($validated['sales_id']);
            if ((int) ($sales->branch_id ?? 0) !== $branchId) {
                throw ValidationException::withMessages([
                    'sales_id' => ['Sales tidak berada di cabang yang dipilih.'],
                ]);
            }
        }
    }

    private function syncCosts(MachineOrder $order, array $costs): void
    {
        $order->costs()->delete();

        foreach ($costs as $cost) {
            $qty = (float) ($cost['qty'] ?? 1);
            $price = (float) ($cost['price'] ?? 0);
            $costType = !empty($cost['cost_type_id']) ? CostType::find($cost['cost_type_id']) : null;

            MachineOrderCost::create([
                'machine_order_id' => $order->id,
                'cost_type_id' => $costType?->id,
                'cost_name_snapshot' => $cost['cost_name'] ?? $costType?->name ?? 'Biaya Tambahan',
                'description' => $cost['description'] ?? null,
                'qty' => $qty,
                'price' => $price,
                'total' => round($qty * $price, 2),
            ]);
        }
    }

    private function syncComponents(MachineOrder $order, ?array $components, Machine $machine, int $orderQty): void
    {
        $existingDeducted = $order->components()
            ->get()
            ->mapWithKeys(fn (MachineOrderComponent $component) => [
                $component->component_id . '|' . $component->component_name_snapshot => (int) $component->stock_deducted_qty,
            ]);

        $order->components()->delete();

        $rows = $components;

        if ($rows === null) {
            $rows = $machine->machineComponents->map(function ($item) use ($orderQty) {
                return [
                    'component_id' => $item->component_id,
                    'component_name' => $item->component?->name,
                    'qty' => (int) $item->qty * $orderQty,
                    'notes' => null,
                    'is_optional' => false,
                ];
            })->values()->all();
        }

        foreach ($rows as $component) {
            $componentModel = !empty($component['component_id']) ? Component::find($component['component_id']) : null;
            $snapshot = $component['component_name'] ?? $componentModel?->name ?? 'Komponen';
            $key = ($componentModel?->id ?? 'null') . '|' . $snapshot;

            MachineOrderComponent::create([
                'machine_order_id' => $order->id,
                'component_id' => $componentModel?->id,
                'component_name_snapshot' => $snapshot,
                'qty' => (int) ($component['qty'] ?? 1),
                'notes' => $component['notes'] ?? null,
                'is_optional' => (bool) ($component['is_optional'] ?? false),
                'stock_deducted_qty' => (int) ($existingDeducted[$key] ?? 0),
            ]);
        }
    }

    private function syncPayments(MachineOrder $order, array $payments, int $defaultReceiverId): void
    {
        $order->payments()->delete();

        foreach ($payments as $payment) {
            MachineOrderPayment::create([
                'machine_order_id' => $order->id,
                'payment_date' => $payment['payment_date'],
                'payment_type' => $payment['payment_type'],
                'amount' => $payment['amount'],
                'payment_method' => $payment['payment_method'] ?? null,
                'reference_number' => $payment['reference_number'] ?? null,
                'notes' => $payment['notes'] ?? null,
                'received_by' => $payment['received_by'] ?? $defaultReceiverId,
            ]);
        }
    }

    private function refreshTotals(MachineOrder $order): void
    {
        $baseTotal = (float) $order->base_price * (int) $order->qty;
        $discountValue = (float) $order->discount_value;

        $discountAmount = match ($order->discount_type) {
            'percent' => round($baseTotal * ($discountValue / 100), 2),
            'amount' => min($discountValue, $baseTotal),
            default => 0,
        };

        $subtotal = round(max($baseTotal - $discountAmount, 0), 2);
        $additionalCostTotal = round((float) $order->costs()->sum('total'), 2);
        $paidTotal = round((float) $order->payments()->get()->sum(function (MachineOrderPayment $payment) {
            $amount = (float) $payment->amount;
            return $payment->payment_type === 'refund' ? -1 * $amount : $amount;
        }), 2);
        $grandTotal = round($subtotal + $additionalCostTotal, 2);
        $remainingTotal = round($grandTotal - $paidTotal, 2);

        $order->update([
            'subtotal' => $subtotal,
            'additional_cost_total' => $additionalCostTotal,
            'grand_total' => $grandTotal,
            'paid_total' => $paidTotal,
            'remaining_total' => max($remainingTotal, 0),
        ]);
    }

    private function generateOrderNumber(int $branchId, string $orderDate): string
    {
        $date = Carbon::parse($orderDate);
        $prefix = sprintf('MO/%02d/%s/', $branchId, $date->format('Ym'));

        $latest = MachineOrder::query()
            ->where('branch_id', $branchId)
            ->whereYear('order_date', $date->year)
            ->whereMonth('order_date', $date->month)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('order_number');

        $nextNumber = 1;

        if ($latest && str_starts_with($latest, $prefix)) {
            $lastSequence = (int) substr($latest, strrpos($latest, '/') + 1);
            $nextNumber = $lastSequence + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    private function findAccessibleOrder(Request $request, $id): MachineOrder
    {
        $user = $request->user();

        $query = MachineOrder::with($this->detailRelations());

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $order = $query->find($id);

        if (!$order) {
            abort(404, 'Machine order tidak ditemukan atau Anda tidak memiliki akses.');
        }

        return $order;
    }

    private function detailRelations(): array
    {
        return [
            'branch',
            'customer',
            'sales',
            'machine.type',
            'costs.costType',
            'payments.receiver',
            'components.component.componentCategory',
            'creator',
            'updater',
        ];
    }

    private function transformListItem(MachineOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_date' => optional($order->order_date)->format('Y-m-d'),
            'branch' => $order->branch?->name,
            'customer' => $order->customer?->full_name,
            'sales' => $order->sales?->name,
            'machine' => $order->machine_name_snapshot,
            'qty' => (int) $order->qty,
            'status' => $order->status,
            'grand_total' => (float) $order->grand_total,
            'paid_total' => (float) $order->paid_total,
            'remaining_total' => (float) $order->remaining_total,
        ];
    }

    private function transformDetail(MachineOrder $order): array
    {
        return [
            'id' => $order->id,
            'branch_id' => $order->branch_id,
            'branch' => $order->branch?->name,
            'order_number' => $order->order_number,
            'order_date' => optional($order->order_date)->format('Y-m-d'),
            'customer_id' => $order->customer_id,
            'customer' => $order->customer?->full_name,
            'sales_id' => $order->sales_id,
            'sales' => $order->sales?->name,
            'machine_id' => $order->machine_id,
            'machine' => [
                'id' => $order->machine?->id,
                'machine_number' => $order->machine_name_snapshot,
                'machine_type' => $order->machine?->type?->name,
            ],
            'qty' => (int) $order->qty,
            'base_price' => (float) $order->base_price,
            'discount_type' => $order->discount_type,
            'discount_value' => (float) $order->discount_value,
            'subtotal' => (float) $order->subtotal,
            'additional_cost_total' => (float) $order->additional_cost_total,
            'grand_total' => (float) $order->grand_total,
            'paid_total' => (float) $order->paid_total,
            'remaining_total' => (float) $order->remaining_total,
            'estimated_start_date' => optional($order->estimated_start_date)->format('Y-m-d'),
            'estimated_finish_date' => optional($order->estimated_finish_date)->format('Y-m-d'),
            'actual_finish_date' => optional($order->actual_finish_date)->format('Y-m-d'),
            'notes' => $order->notes,
            'internal_notes' => $order->internal_notes,
            'status' => $order->status,
            'created_by' => $order->creator?->name,
            'updated_by' => $order->updater?->name,
            'costs' => $order->costs->map(fn (MachineOrderCost $cost) => [
                'id' => $cost->id,
                'cost_type_id' => $cost->cost_type_id,
                'cost_type' => $cost->costType?->name,
                'cost_name' => $cost->cost_name_snapshot,
                'description' => $cost->description,
                'qty' => (float) $cost->qty,
                'price' => (float) $cost->price,
                'total' => (float) $cost->total,
            ])->values()->all(),
            'payments' => $order->payments->map(fn (MachineOrderPayment $payment) => [
                'id' => $payment->id,
                'payment_date' => optional($payment->payment_date)->format('Y-m-d'),
                'payment_type' => $payment->payment_type,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'notes' => $payment->notes,
                'received_by' => $payment->receiver?->name,
            ])->values()->all(),
            'components' => $order->components->map(fn (MachineOrderComponent $component) => [
                'id' => $component->id,
                'component_id' => $component->component_id,
                'component_name' => $component->component_name_snapshot,
                'component_category' => $component->component?->componentCategory?->name,
                'qty' => (int) $component->qty,
                'stock_deducted_qty' => (int) $component->stock_deducted_qty,
                'notes' => $component->notes,
                'is_optional' => (bool) $component->is_optional,
            ])->values()->all(),
        ];
    }
}
