<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportSalesRecapController extends Controller
{
    public function plate(Request $request)
    {
        return $this->indexByType($request, 'plate');
    }

    public function cutting(Request $request)
    {
        return $this->indexByType($request, 'cutting');
    }

    private function indexByType(Request $request, string $productType)
    {
        $user = $request->user();
        $branchId = $user->isSuperAdmin() && $request->filled('branch_id')
            ? $request->integer('branch_id')
            : $user->branch_id;

        $query = InvoiceItem::query()
            ->with([
                'invoice.branch',
                'invoice.customer',
                'invoice.machine',
                'invoice.user',
                'plateVariant.plateType',
                'plateVariant.size',
                'cuttingPrice.machineType',
                'cuttingPrice.plateType',
                'cuttingPrice.size',
            ])
            ->where('product_type', $productType)
            ->whereHas('invoice', function ($invoiceQuery) use ($request, $branchId) {
                $invoiceQuery
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->when($request->filled('number'), fn ($query) => $query->where('invoice_number', 'like', '%' . $request->input('number') . '%'))
                    ->when($request->filled('date_from'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->input('date_from')))
                    ->when($request->filled('date_to'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->input('date_to')))
                    ->when($request->filled('customer'), function ($query) use ($request) {
                        $query->whereHas('customer', function ($customerQuery) use ($request) {
                            $customerQuery->where('full_name', 'like', '%' . $request->input('customer') . '%');
                        });
                    });
            })
            ->latest('id');

        $descriptionFilter = strtolower(trim((string) $request->input('description', '')));

        $rows = $query->get()
            ->map(function (InvoiceItem $item) {
                $invoice = $item->invoice;
                $descriptor = $this->buildDescriptor($item);
                $statusKey = $this->normalizeProductionStatus($invoice?->status);

                return [
                    'invoice_id' => $invoice?->id,
                    'number' => $invoice?->invoice_number ?? '-',
                    'date' => optional($invoice?->transaction_date)->format('Y-m-d'),
                    'customer' => $invoice?->customer?->full_name ?? '-',
                    'branch' => $invoice?->branch?->name ?? '-',
                    'machine' => $invoice?->machine?->machine_number ?: '-',
                    'petugas' => $invoice?->user?->name ?? '-',
                    'item_type' => $item->product_type === 'plate' ? 'Plat' : 'Cutting',
                    'description' => $descriptor,
                    'qty' => (int) $item->qty,
                    'minutes' => (int) $item->minutes,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                    'production_status' => $this->formatStatusLabel($statusKey),
                    'production_status_key' => $statusKey,
                ];
            })
            ->filter(function (array $row) use ($descriptionFilter) {
                if ($descriptionFilter === '') {
                    return true;
                }

                return str_contains(strtolower($row['description']), $descriptionFilter);
            })
            ->sortByDesc(fn (array $row) => ($row['date'] ?? '') . '|' . ($row['invoice_id'] ?? 0))
            ->values();

        $summary = [
            'total_rows' => $rows->count(),
            'total_qty' => (int) $rows->sum('qty'),
            'total_minutes' => (int) $rows->sum('minutes'),
            'total_cancel' => $rows
                ->filter(fn (array $row) => $row['production_status_key'] === 'cancelled')
                ->pluck('invoice_id')
                ->filter()
                ->unique()
                ->count(),
            'total_omzet' => (float) $rows
                ->reject(fn (array $row) => $row['production_status_key'] === 'cancelled')
                ->sum('subtotal'),
            'product_type' => $productType,
        ];

        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $page = max((int) $request->input('page', 1), 1);
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page
        );

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'summary' => $summary,
        ]);
    }

    private function buildDescriptor(InvoiceItem $item): string
    {
        if ($item->product_type === 'plate') {
            return trim(
                ($item->plateVariant?->plateType?->name ?? 'Plat') . ' ' .
                ($item->plateVariant?->size?->value ?? '')
            );
        }

        $descriptor = trim(
            ($item->cuttingPrice?->machineType?->name ?? 'Cutting') . ' / ' .
            ($item->cuttingPrice?->plateType?->name ?? '') . ' / ' .
            ($item->cuttingPrice?->size?->value ?? '')
        );

        return trim($descriptor . ' / ' . $this->formatPricingMode($item->pricing_mode), ' /');
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

    private function normalizeProductionStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'pending' => 'pending',
            'in-process', 'in process', 'diproses', 'process', 'waiting', 'waiting list' => 'in-process',
            'completed', 'selesai', 'lunas' => 'completed',
            'cancel', 'cancelled', 'canceled' => 'cancelled',
            default => strtolower(trim((string) $status)) ?: 'pending',
        };
    }

    private function formatStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'in-process' => 'In Process',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucwords(str_replace('-', ' ', (string) $status)),
        };
    }
}
