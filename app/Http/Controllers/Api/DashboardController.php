<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $branchId = $user->isSuperAdmin() && $request->filled('branch_id')
            ? $request->integer('branch_id')
            : $user->branch_id;

        $cacheKey = sprintf(
            'dashboard_summary:user_%s:branch_%s',
            $user->id,
            $branchId ?: 'all'
        );

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, now()->addMinute(), function () use ($branchId) {
            return $this->buildSummaryPayload($branchId);
        });

        return response()->json($payload);
    }

    private function buildSummaryPayload(?int $branchId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $invoiceQuery = Invoice::query()
            ->with(['branch', 'customer', 'payments'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        $invoices = (clone $invoiceQuery)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $monthInvoices = $invoices->filter(function (Invoice $invoice) use ($startOfMonth, $endOfMonth) {
            $date = $invoice->transaction_date instanceof Carbon
                ? $invoice->transaction_date
                : Carbon::parse($invoice->transaction_date);

            return $date->between($startOfMonth, $endOfMonth);
        });

        $stats = [
            'total_invoice_bulan_ini' => $monthInvoices->count(),
            'total_omzet_bulan_ini' => (float) $monthInvoices->sum('grand_total'),
            'total_piutang' => (float) $invoices->sum(function (Invoice $invoice) {
                return max((float) $invoice->grand_total - (float) $invoice->payments->sum('amount'), 0);
            }),
            'produksi_berjalan' => $invoices->filter(function (Invoice $invoice) {
                return in_array($this->normalizeProductionStatus($invoice->status), ['pending', 'in-process'], true);
            })->count(),
            'pelanggan_aktif' => $monthInvoices->pluck('customer_id')->filter()->unique()->count(),
            'invoice_lunas' => $monthInvoices->filter(fn (Invoice $invoice) => $this->resolvePaymentStatus($invoice) === 'lunas')->count(),
            'invoice_dp' => $monthInvoices->filter(fn (Invoice $invoice) => $this->resolvePaymentStatus($invoice) === 'dp')->count(),
            'invoice_belum_bayar' => $monthInvoices->filter(fn (Invoice $invoice) => $this->resolvePaymentStatus($invoice) === 'belum-bayar')->count(),
        ];

        $revenueChart = [
            'labels' => [],
            'data' => [],
        ];

        foreach (range(5, 1) as $offset) {
            $month = $now->copy()->startOfMonth()->subMonths($offset);
            $label = $month->translatedFormat('M Y');

            $monthTotal = $invoices->filter(function (Invoice $invoice) use ($month) {
                $date = $invoice->transaction_date instanceof Carbon
                    ? $invoice->transaction_date
                    : Carbon::parse($invoice->transaction_date);

                return $date->isSameMonth($month);
            })->sum('grand_total');

            $revenueChart['labels'][] = $label;
            $revenueChart['data'][] = (float) $monthTotal;
        }

        $currentMonth = $now->copy()->startOfMonth();
        $revenueChart['labels'][] = $currentMonth->translatedFormat('M Y');
        $revenueChart['data'][] = (float) $monthInvoices->sum('grand_total');

        $prodChart = [
            'pending' => $invoices->filter(fn (Invoice $invoice) => $this->normalizeProductionStatus($invoice->status) === 'pending')->count(),
            'in_process' => $invoices->filter(fn (Invoice $invoice) => $this->normalizeProductionStatus($invoice->status) === 'in-process')->count(),
            'completed' => $invoices->filter(fn (Invoice $invoice) => $this->normalizeProductionStatus($invoice->status) === 'completed')->count(),
            'cancelled' => $invoices->filter(fn (Invoice $invoice) => $this->normalizeProductionStatus($invoice->status) === 'cancelled')->count(),
        ];

        $recentInvoices = $invoices->take(5)->map(function (Invoice $invoice) {
            return [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->full_name,
                'branch' => $invoice->branch?->name,
                'grand_total' => (float) $invoice->grand_total,
                'status' => $this->resolvePaymentStatus($invoice),
                'date' => optional($invoice->transaction_date)->format('Y-m-d'),
            ];
        })->values()->all();

        $topCustomers = $this->buildTopCustomers($branchId, $now);

        return [
            'stats' => $stats,
            'revenue_chart' => $revenueChart,
            'production_chart' => $prodChart,
            'recent_invoices' => $recentInvoices,
            'top_customers' => $topCustomers,
            'generated_at' => now()->toIso8601String(),
            'cache_ttl_seconds' => 60,
        ];
    }

    private function buildTopCustomers(?int $branchId, Carbon $now): array
    {
        $currentStart = $now->copy()->startOfMonth();
        $currentEnd = $now->copy()->endOfMonth();
        $previousStart = $now->copy()->subMonth()->startOfMonth();
        $previousEnd = $now->copy()->subMonth()->endOfMonth();

        $customers = Customer::query()
            ->with(['branch', 'sales'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        $currentRanking = Invoice::query()
            ->selectRaw('customer_id, SUM(grand_total) as total_amount')
            ->whereBetween('transaction_date', [$currentStart->toDateString(), $currentEnd->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('customer_id')
            ->orderByDesc('total_amount')
            ->get()
            ->values();

        $previousRanking = Invoice::query()
            ->selectRaw('customer_id, SUM(grand_total) as total_amount')
            ->whereBetween('transaction_date', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('customer_id')
            ->orderByDesc('total_amount')
            ->get()
            ->values();

        $previousRanks = $previousRanking->pluck('customer_id')->values();

        return $currentRanking->take(5)->map(function ($row, $index) use ($customers, $previousRanks) {
            $customer = $customers->firstWhere('id', $row->customer_id);
            $previousIndex = $previousRanks->search($row->customer_id);

            return [
                'id' => $row->customer_id,
                'name' => $customer?->full_name ?? 'Pelanggan',
                'branch' => $customer?->branch?->name ?? '-',
                'ranking_now' => $index + 1,
                'ranking_last' => $previousIndex === false ? ($index + 1) : ($previousIndex + 1),
                'total_amount' => (float) $row->total_amount,
            ];
        })->values()->all();
    }

    private function resolvePaymentStatus(Invoice $invoice): string
    {
        $paid = (float) $invoice->payments->sum('amount');
        $grandTotal = (float) $invoice->grand_total;

        if ($paid >= $grandTotal && $grandTotal > 0) {
            return 'lunas';
        }

        if ($paid > 0) {
            return 'dp';
        }

        return 'belum-bayar';
    }

    private function normalizeProductionStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'pending' => 'pending',
            'confirmed' => 'confirmed',
            'waiting', 'waiting list', 'in-process', 'in process', 'diproses' => 'in-process',
            'completed', 'selesai', 'lunas' => 'completed',
            'cancel', 'cancelled', 'canceled' => 'cancelled',
            default => 'confirmed',
        };
    }
}
