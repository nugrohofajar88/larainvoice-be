<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BranchInvoiceCounter;
use App\Models\Component;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderAssignment;
use App\Models\ServiceOrderComponent;
use App\Models\ServiceOrderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderController extends Controller
{
    private const STATUSES = ['draft', 'confirmed', 'in_progress', 'completed', 'invoiced', 'cancelled'];
    private const INVOICEABLE_STATUSES = ['confirmed', 'in_progress', 'completed'];
    private const ASSIGNMENT_ROLES = ['lead', 'teknisi', 'trainer', 'helper'];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = ServiceOrder::query()
            ->with([
                'branch',
                'customer',
                'assignedUsers',
                'creator',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'service_orders.customer_id')
            ->select('service_orders.*');

        if (!$user->isSuperAdmin()) {
            $query->where('service_orders.branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('service_orders.branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('order_type')) {
            $query->where('service_orders.order_type', $request->input('order_type'));
        }

        if ($request->filled('customer_id')) {
            $query->where('service_orders.customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('service_orders.status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('service_orders.order_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('service_orders.order_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('service_orders.order_number', 'like', "%{$search}%")
                    ->orWhere('service_orders.title', 'like', "%{$search}%")
                    ->orWhere('customers.full_name', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowedSorts = ['id', 'order_number', 'order_date', 'status', 'created_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query->orderBy('service_orders.' . $sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 15), 100);

        return response()->json(
            $query->paginate($perPage)->through(fn (ServiceOrder $order) => $this->transformListItem($order))
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
        $validated = $this->validatePayload($request, $user, false);

        $order = DB::transaction(function () use ($validated, $user) {
            $branchId = $user->isSuperAdmin()
                ? (int) $validated['branch_id']
                : (int) $user->branch_id;

            $this->assertBranchConsistency($branchId, $validated);

            $orderNumber = $this->generateOrderNumber($branchId, $validated['order_date']);

            $order = ServiceOrder::create([
                'branch_id' => $branchId,
                'order_number' => $orderNumber,
                'order_type' => $validated['order_type'],
                'order_date' => $validated['order_date'],
                'customer_id' => $validated['customer_id'],
                'status' => $validated['status'] ?? 'draft',
                'title' => $validated['title'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'location' => $validated['location'] ?? null,
                'planned_start_date' => $validated['planned_start_date'] ?? null,
                'duration_days' => $validated['duration_days'] ?? null,
                'actual_start_date' => $validated['actual_start_date'] ?? null,
                'actual_finish_date' => $validated['actual_finish_date'] ?? null,
                'completion_notes' => $validated['completion_notes'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            ServiceOrderLog::create([
                'service_order_id' => $order->id,
                'user_id' => $user->id,
                'action_type' => 'created',
                'from_status' => null,
                'to_status' => $order->status,
                'note' => 'Order jasa dibuat.',
                'meta' => [
                    'order_number' => $order->order_number,
                    'order_type' => $order->order_type,
                ],
            ]);

            $this->syncAssignments($order, $validated['assignments'] ?? []);

            if ($order->order_type === 'service') {
                $this->syncComponents($order, $validated['components'] ?? []);
            }

            return $order->fresh($this->detailRelations());
        });

        return response()->json([
            'message' => 'Order jasa berhasil dibuat.',
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

            $consistencyPayload = array_merge([
                'customer_id' => $order->customer_id,
            ], $validated);
            $this->assertBranchConsistency($branchId, $consistencyPayload);

            $previousStatus = (string) $order->status;
            $nextStatus = (string) ($validated['status'] ?? $order->status);

            if (
                array_key_exists('status', $validated)
                && (string) ($validated['status'] ?? '') !== ''
                && !$this->isAllowedStatusTransition($previousStatus, $nextStatus)
            ) {
                throw ValidationException::withMessages([
                    'status' => ['Status order jasa hanya bisa bergerak maju dan tidak bisa mundur.'],
                ]);
            }

            $updateData = [
                'branch_id' => $branchId,
                'updated_by' => $user->id,
            ];

            foreach ([
                'order_date',
                'customer_id',
                'title',
                'category',
                'description',
                'location',
                'planned_start_date',
                'duration_days',
                'actual_start_date',
                'actual_finish_date',
                'completion_notes',
                'notes',
                'internal_notes',
                'status',
            ] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field];
                }
            }

            $order->update($updateData);

            if (
                array_key_exists('status', $validated)
                && (string) ($validated['status'] ?? '') !== ''
                && $previousStatus !== (string) $order->status
            ) {
                ServiceOrderLog::create([
                    'service_order_id' => $order->id,
                    'user_id' => $user->id,
                    'action_type' => 'status_changed',
                    'from_status' => $previousStatus,
                    'to_status' => (string) $order->status,
                    'note' => null,
                    'meta' => ['order_number' => $order->order_number],
                ]);
            }

            if (array_key_exists('assignments', $validated)) {
                $this->syncAssignments($order, $validated['assignments'] ?? []);
            }

            if ($order->order_type === 'service' && array_key_exists('components', $validated)) {
                $this->syncComponents($order, $validated['components'] ?? []);
            }

            return $order->fresh($this->detailRelations());
        });

        return response()->json([
            'message' => 'Order jasa berhasil diperbarui.',
            'data' => $this->transformDetail($order),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $order = $this->findAccessibleOrder($request, $id);
        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'note' => ['nullable', 'string'],
        ]);

        $previousStatus = (string) $order->status;
        $nextStatus = (string) $validated['status'];

        if ($previousStatus === $nextStatus) {
            return response()->json([
                'message' => 'Status order jasa tidak berubah.',
                'data' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'status_label' => $this->formatStatus($order->status),
                ],
            ]);
        }

        if (!$this->isAllowedStatusTransition($previousStatus, $nextStatus)) {
            return response()->json([
                'message' => 'Status order jasa hanya bisa bergerak maju dan tidak bisa mundur.',
            ], 422);
        }

        if ($nextStatus === 'invoiced') {
            return response()->json([
                'message' => 'Status invoiced hanya bisa di-set lewat pembuatan invoice.',
            ], 422);
        }

        $order = DB::transaction(function () use ($order, $user, $previousStatus, $nextStatus, $validated) {
            $order->update([
                'status' => $nextStatus,
                'updated_by' => $user->id,
            ]);

            ServiceOrderLog::create([
                'service_order_id' => $order->id,
                'user_id' => $user->id,
                'action_type' => 'status_changed',
                'from_status' => $previousStatus,
                'to_status' => $nextStatus,
                'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
                'meta' => ['order_number' => $order->order_number],
            ]);

            return $order->fresh($this->detailRelations());
        });

        return response()->json([
            'message' => 'Status order jasa berhasil diperbarui.',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'status_label' => $this->formatStatus($order->status),
            ],
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $order = $this->findAccessibleOrder($request, $id);

        if ($order->invoice_id) {
            return response()->json([
                'message' => 'Order jasa yang sudah terhubung invoice tidak bisa dihapus.',
            ], 422);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order jasa berhasil dihapus.',
        ]);
    }

    private function validatePayload(Request $request, $user, bool $isUpdate, ?ServiceOrder $order = null): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'branch_id' => $user->isSuperAdmin() ? [$required, 'exists:branches,id'] : ['nullable'],
            'order_type' => $isUpdate ? ['prohibited'] : ['required', 'in:service,training'],
            'order_date' => [$required, 'date'],
            'customer_id' => [$required, 'exists:customers,id'],
            'title' => [$required, 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'planned_start_date' => ['nullable', 'date'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_finish_date' => ['nullable', 'date'],
            'completion_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:' . implode(',', self::STATUSES)],

            'assignments' => ['sometimes', 'array'],
            'assignments.*.user_id' => ['required_with:assignments', 'exists:users,id'],
            'assignments.*.role' => ['nullable', 'in:' . implode(',', self::ASSIGNMENT_ROLES)],
            'assignments.*.notes' => ['nullable', 'string'],

            'components' => ['sometimes', 'array'],
            'components.*.component_id' => ['nullable', 'exists:components,id'],
            'components.*.component_name' => ['nullable', 'string', 'max:255'],
            'components.*.qty' => ['required_with:components', 'integer', 'min:1'],
            'components.*.notes' => ['nullable', 'string'],
            'components.*.billable' => ['nullable', 'boolean'],
        ]);
    }

    private function assertBranchConsistency(int $branchId, array $validated): void
    {
        $customerId = $validated['customer_id'] ?? null;

        if ($customerId) {
            $customer = Customer::findOrFail($customerId);

            if ((int) $customer->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Pelanggan tidak berada di cabang yang dipilih.'],
                ]);
            }
        }

        $assignments = $validated['assignments'] ?? [];
        $userIds = collect($assignments)->pluck('user_id')->filter()->unique()->values();

        if ($userIds->isNotEmpty()) {
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');
            foreach ($userIds as $uid) {
                $u = $users->get($uid);
                if (!$u) {
                    continue;
                }
                if ((int) ($u->branch_id ?? 0) !== $branchId && !$u->isSuperAdmin()) {
                    throw ValidationException::withMessages([
                        'assignments' => ['User yang ditugaskan harus berada di cabang yang sama dengan order.'],
                    ]);
                }
            }
        }
    }

    private function syncAssignments(ServiceOrder $order, array $assignments): void
    {
        $order->assignments()->delete();

        $seen = [];
        foreach ($assignments as $assignment) {
            $userId = (int) ($assignment['user_id'] ?? 0);
            if (!$userId || isset($seen[$userId])) {
                continue;
            }
            $seen[$userId] = true;

            ServiceOrderAssignment::create([
                'service_order_id' => $order->id,
                'user_id' => $userId,
                'role' => $assignment['role'] ?? null,
                'notes' => $assignment['notes'] ?? null,
            ]);
        }
    }

    private function syncComponents(ServiceOrder $order, array $components): void
    {
        $order->components()->delete();

        foreach ($components as $component) {
            $componentModel = !empty($component['component_id']) ? Component::find($component['component_id']) : null;
            $snapshot = $component['component_name'] ?? $componentModel?->name ?? 'Komponen';

            ServiceOrderComponent::create([
                'service_order_id' => $order->id,
                'component_id' => $componentModel?->id,
                'component_name_snapshot' => $snapshot,
                'qty' => (int) ($component['qty'] ?? 1),
                'notes' => $component['notes'] ?? null,
                'billable' => array_key_exists('billable', $component) ? (bool) $component['billable'] : true,
            ]);
        }
    }

    public function createInvoice(Request $request, $id)
    {
        $user = $request->user();
        $order = $this->findAccessibleOrder($request, $id);
        $validated = $request->validate([
            'transaction_date' => ['nullable', 'date'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grand_total' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.source_component_id' => ['nullable', 'exists:service_order_components,id'],
        ]);

        if (!in_array($order->status, self::INVOICEABLE_STATUSES, true)) {
            return response()->json([
                'message' => 'Invoice hanya bisa dibuat dari order jasa yang statusnya confirmed, in_progress, atau completed.',
            ], 422);
        }

        if ($order->invoice_id) {
            $existing = Invoice::find($order->invoice_id);
            return response()->json([
                'message' => 'Invoice untuk order jasa ini sudah pernah dibuat.',
                'data' => $existing ? [
                    'invoice_id' => $existing->id,
                    'invoice_number' => $existing->invoice_number,
                ] : null,
            ], 422);
        }

        $invoice = DB::transaction(function () use ($order, $user, $validated) {
            $transactionDate = $validated['transaction_date']
                ?? optional($order->order_date)->format('Y-m-d')
                ?? now()->toDateString();

            $itemsTotal = collect($validated['items'])->sum(fn ($item) => (float) $item['qty'] * (float) $item['price']);
            $requestedGrandTotal = (float) $validated['grand_total'];
            $grandTotal = max(min($requestedGrandTotal, $itemsTotal), 0);
            $discountAmount = round(max($itemsTotal - $grandTotal, 0), 2);
            $discountPct = $itemsTotal > 0
                ? round(($discountAmount / $itemsTotal) * 100, 2)
                : 0;

            $invoice = Invoice::create([
                'invoice_number' => $this->generateInvoiceNumber((int) $order->branch_id, $transactionDate),
                'invoice_type' => 'service_order',
                'source_type' => 'service_order',
                'source_id' => $order->id,
                'branch_id' => $order->branch_id,
                'customer_id' => $order->customer_id,
                'machine_id' => null,
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
                'status' => 'Completed',
                'total_amount' => $itemsTotal,
                'discount_pct' => $discountPct,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? $this->buildInvoiceNotes($order),
            ]);

            foreach ($validated['items'] as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['price'];
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_type' => $order->order_type === 'training' ? 'training' : 'service',
                    'item_type' => 'service_order_item',
                    'source_type' => 'service_order',
                    'source_id' => $order->id,
                    'description' => $item['description'],
                    'unit' => $item['unit'] ?? 'jasa',
                    'qty' => $qty,
                    'minutes' => 0,
                    'price' => $price,
                    'discount_pct' => 0,
                    'discount_amount' => 0,
                    'subtotal' => round($qty * $price, 2),
                ]);
            }

            $order->update([
                'invoice_id' => $invoice->id,
                'status' => 'invoiced',
                'updated_by' => $user->id,
            ]);

            ServiceOrderLog::create([
                'service_order_id' => $order->id,
                'user_id' => $user->id,
                'action_type' => 'invoice_created',
                'from_status' => null,
                'to_status' => 'invoiced',
                'note' => 'Invoice diterbitkan: ' . $invoice->invoice_number,
                'meta' => [
                    'order_number' => $order->order_number,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            ]);

            return $invoice->fresh(['items']);
        });

        return response()->json([
            'message' => 'Invoice dari order jasa berhasil dibuat.',
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'grand_total' => (float) $invoice->grand_total,
            ],
        ], 201);
    }

    private function generateOrderNumber(int $branchId, string $orderDate): string
    {
        $date = Carbon::parse($orderDate);
        $prefix = sprintf('OJ/%02d/%s/', $branchId, $date->format('Ym'));

        $latest = ServiceOrder::query()
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

    private function findAccessibleOrder(Request $request, $id): ServiceOrder
    {
        $user = $request->user();

        $query = ServiceOrder::with($this->detailRelations());

        if (!$user->isSuperAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $order = $query->find($id);

        if (!$order) {
            abort(404, 'Order jasa tidak ditemukan atau Anda tidak memiliki akses.');
        }

        return $order;
    }

    private function detailRelations(): array
    {
        return [
            'branch',
            'customer',
            'assignments.user',
            'components.component.componentCategory',
            'creator',
            'updater',
            'invoice',
            'logs.user',
        ];
    }

    private function transformListItem(ServiceOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'order_type_label' => $this->formatOrderType($order->order_type),
            'order_date' => optional($order->order_date)->format('Y-m-d'),
            'branch' => $order->branch?->name,
            'customer' => $order->customer?->full_name,
            'title' => $order->title,
            'location' => $order->location,
            'planned_start_date' => optional($order->planned_start_date)->format('Y-m-d'),
            'status' => $order->status,
            'status_label' => $this->formatStatus($order->status),
            'assigned_count' => $order->assignedUsers->count(),
            'has_invoice' => !empty($order->invoice_id),
        ];
    }

    private function transformDetail(ServiceOrder $order): array
    {
        return [
            'id' => $order->id,
            'branch_id' => $order->branch_id,
            'branch' => $order->branch?->name,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'order_type_label' => $this->formatOrderType($order->order_type),
            'order_date' => optional($order->order_date)->format('Y-m-d'),
            'customer_id' => $order->customer_id,
            'customer' => $order->customer?->full_name,
            'status' => $order->status,
            'status_label' => $this->formatStatus($order->status),
            'title' => $order->title,
            'category' => $order->category,
            'description' => $order->description,
            'location' => $order->location,
            'planned_start_date' => optional($order->planned_start_date)->format('Y-m-d'),
            'duration_days' => $order->duration_days,
            'actual_start_date' => optional($order->actual_start_date)->format('Y-m-d'),
            'actual_finish_date' => optional($order->actual_finish_date)->format('Y-m-d'),
            'completion_notes' => $order->completion_notes,
            'notes' => $order->notes,
            'internal_notes' => $order->internal_notes,
            'invoice' => $order->invoice ? [
                'id' => $order->invoice->id,
                'invoice_number' => $order->invoice->invoice_number,
                'status' => $order->invoice->status,
                'grand_total' => (float) $order->invoice->grand_total,
            ] : null,
            'created_by' => $order->creator?->name,
            'updated_by' => $order->updater?->name,
            'assignments' => $order->assignments->map(fn (ServiceOrderAssignment $a) => [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'user' => $a->user?->name,
                'role' => $a->role,
                'role_label' => $this->formatRole($a->role),
                'notes' => $a->notes,
            ])->values()->all(),
            'components' => $order->components->map(fn (ServiceOrderComponent $c) => [
                'id' => $c->id,
                'component_id' => $c->component_id,
                'component_name' => $c->component_name_snapshot,
                'component_category' => $c->component?->componentCategory?->name,
                'qty' => (int) $c->qty,
                'notes' => $c->notes,
                'billable' => (bool) $c->billable,
            ])->values()->all(),
            'logs' => $order->logs->map(function (ServiceOrderLog $log) {
                return [
                    'id' => $log->id,
                    'action_type' => $log->action_type,
                    'action_label' => match ($log->action_type) {
                        'created' => 'Order Dibuat',
                        'status_changed' => 'Perubahan Status',
                        'invoice_created' => 'Invoice Diterbitkan',
                        default => ucwords(str_replace('_', ' ', (string) $log->action_type)),
                    },
                    'from_status' => $log->from_status,
                    'from_status_label' => $this->formatStatus($log->from_status),
                    'to_status' => $log->to_status,
                    'to_status_label' => $this->formatStatus($log->to_status),
                    'note' => $log->note,
                    'handled_by' => $log->user?->name,
                    'user_id' => $log->user_id,
                    'meta' => $log->meta ?? [],
                    'created_at' => optional($log->created_at)->format('Y-m-d H:i:s'),
                ];
            })->values()->all(),
        ];
    }

    private function buildInvoiceNotes(ServiceOrder $order): string
    {
        $typeLabel = $this->formatOrderType($order->order_type);
        return "Tagihan diterbitkan dari {$typeLabel} " . ($order->order_number ?: '-') . ' - ' . ($order->title ?: '');
    }

    private function formatStatus(?string $status): ?string
    {
        if (!$status) {
            return null;
        }

        return match ((string) $status) {
            'draft' => 'Draft',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'invoiced' => 'Invoiced',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('_', ' ', (string) $status)),
        };
    }

    private function formatOrderType(?string $type): ?string
    {
        return match ($type) {
            'service' => 'Order Servis',
            'training' => 'Order Pelatihan',
            default => null,
        };
    }

    private function formatRole(?string $role): ?string
    {
        return match ($role) {
            'lead' => 'Lead',
            'teknisi' => 'Teknisi',
            'trainer' => 'Trainer',
            'helper' => 'Helper',
            default => $role,
        };
    }

    private function isAllowedStatusTransition(?string $currentStatus, ?string $targetStatus): bool
    {
        $currentStatus = (string) $currentStatus;
        $targetStatus = (string) $targetStatus;

        if ($currentStatus === '' || $targetStatus === '') {
            return false;
        }

        if ($currentStatus === $targetStatus) {
            return true;
        }

        if (in_array($currentStatus, ['invoiced', 'cancelled'], true)) {
            return false;
        }

        if ($targetStatus === 'cancelled') {
            return true;
        }

        $flow = ['draft', 'confirmed', 'in_progress', 'completed', 'invoiced'];

        $currentIndex = array_search($currentStatus, $flow, true);
        $targetIndex = array_search($targetStatus, $flow, true);

        if ($currentIndex === false || $targetIndex === false) {
            return false;
        }

        return $targetIndex > $currentIndex;
    }
}
