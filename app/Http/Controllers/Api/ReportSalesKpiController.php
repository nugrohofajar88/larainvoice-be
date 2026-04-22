<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportSalesKpiController extends Controller
{
    private const DEFAULT_MONTHLY_TARGET = 150000000;
    private const DEFAULT_YEARLY_TARGET = 1800000000;

    public function index(Request $request)
    {
        $user = $request->user();
        $periodType = $request->input('period_type', 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        [$startDate, $endDate] = $this->resolvePeriod($periodType, $year, $month);

        $branchId = $user->isSuperAdmin() && $request->filled('branch_id')
            ? $request->integer('branch_id')
            : $user->branch_id;

        $salesUsers = User::query()
            ->with('branch')
            ->whereHas('role', fn ($query) => $query->where('name', 'sales'))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%' . $request->input('name') . '%'))
            ->orderBy('name')
            ->get();

        $invoiceStats = Invoice::query()
            ->selectRaw('user_id, COUNT(*) as total_invoice, COALESCE(SUM(grand_total), 0) as total_omzet')
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotIn('status', ['Cancel'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $target = $periodType === 'yearly'
            ? (float) env('REPORT_SALES_KPI_TARGET_YEARLY', self::DEFAULT_YEARLY_TARGET)
            : (float) env('REPORT_SALES_KPI_TARGET_MONTHLY', self::DEFAULT_MONTHLY_TARGET);

        $rows = $salesUsers->map(function (User $salesUser) use ($invoiceStats, $target) {
            $stat = $invoiceStats->get($salesUser->id);
            $totalInvoice = (int) ($stat->total_invoice ?? 0);
            $totalOmzet = (float) ($stat->total_omzet ?? 0);

            return [
                'id' => $salesUser->id,
                'name' => $salesUser->name,
                'branch' => $salesUser->branch?->name ?? '-',
                'total_invoice' => $totalInvoice,
                'total_omzet' => $totalOmzet,
                'target' => $target,
                'achievement_pct' => $target > 0 ? ($totalOmzet / $target) * 100 : 0,
            ];
        })->sortByDesc('total_omzet')->values();

        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
        $page = max((int) $request->input('page', 1), 1);

        return response()->json([
            'data' => $rows->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => (int) ceil(max($rows->count(), 1) / $perPage),
            'per_page' => $perPage,
            'total' => $rows->count(),
            'summary' => [
                'period_type' => $periodType,
                'year' => $year,
                'month' => $month,
                'period_label' => $periodType === 'yearly'
                    ? (string) $year
                    : Carbon::create($year, max(min($month, 12), 1), 1)->translatedFormat('F Y'),
                'total_invoice' => (int) $rows->sum('total_invoice'),
                'total_omzet' => (float) $rows->sum('total_omzet'),
                'target' => $target,
            ],
        ]);
    }

    private function resolvePeriod(string $periodType, int $year, int $month): array
    {
        if ($periodType === 'yearly') {
            $start = Carbon::create($year, 1, 1)->startOfYear();
            $end = Carbon::create($year, 1, 1)->endOfYear();

            return [$start, $end];
        }

        $safeMonth = max(min($month, 12), 1);
        $start = Carbon::create($year, $safeMonth, 1)->startOfMonth();
        $end = Carbon::create($year, $safeMonth, 1)->endOfMonth();

        return [$start, $end];
    }
}
