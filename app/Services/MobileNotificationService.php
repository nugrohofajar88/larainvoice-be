<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Component;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlateVariant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobileNotificationService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $fcm,
    ) {
    }

    public function notifyInvoiceCreated(Invoice $invoice): void
    {
        $invoice->loadMissing(['branch', 'customer', 'user']);

        $this->createForBranchAdmins(
            (int) $invoice->branch_id,
            'invoice_created',
            'Invoice baru dibuat',
            sprintf(
                '%s untuk %s senilai %s telah dibuat oleh %s.',
                $invoice->invoice_number,
                $invoice->customer?->full_name ?? 'pelanggan',
                number_format((float) $invoice->grand_total, 0, ',', '.'),
                $invoice->user?->name ?? 'system'
            ),
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->full_name,
                'grand_total' => (float) $invoice->grand_total,
            ]
        );
    }

    public function notifyPaymentCreated(Payment $payment): void
    {
        $payment->loadMissing(['invoice.customer', 'user', 'branch']);

        $this->createForBranchAdmins(
            (int) $payment->branch_id,
            'payment_created',
            'Pembayaran baru dicatat',
            sprintf(
                'Pembayaran %s untuk invoice %s oleh %s telah dicatat.',
                number_format((float) $payment->amount, 0, ',', '.'),
                $payment->invoice?->invoice_number ?? '-',
                $payment->user?->name ?? 'system'
            ),
            [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_number' => $payment->invoice?->invoice_number,
                'customer' => $payment->invoice?->customer?->full_name,
                'amount' => (float) $payment->amount,
                'is_dp' => (bool) $payment->is_dp,
            ]
        );
    }

    public function notifyLowStockForPlateVariant(PlateVariant $plateVariant): void
    {
        $plateVariant->loadMissing(['branch.setting', 'plateType', 'size']);

        $threshold = (int) ($plateVariant->branch?->setting?->minimum_stock ?? 0);
        if ($threshold < 0) {
            $threshold = 0;
        }

        $currentQty = (int) DB::table('stock_movements')
            ->where('plate_variant_id', $plateVariant->id)
            ->sum('qty');

        if ($currentQty > $threshold) {
            return;
        }

        $stockKey = 'plate_variant:' . $plateVariant->id;
        if ($this->hasRecentStockAlert((int) $plateVariant->branch_id, $stockKey)) {
            return;
        }

        $this->createForBranchAdmins(
            (int) $plateVariant->branch_id,
            'stock_below_minimum',
            'Stok plat mencapai minimum',
            sprintf(
                'Stok plat %s %s saat ini %d dan sudah menyentuh batas minimum cabang (%d).',
                $plateVariant->plateType?->name ?? 'Plat',
                $plateVariant->size?->value ?? '',
                $currentQty,
                $threshold
            ),
            [
                'stock_key' => $stockKey,
                'item_type' => 'plate_variant',
                'item_id' => $plateVariant->id,
                'item_name' => trim(($plateVariant->plateType?->name ?? 'Plat') . ' ' . ($plateVariant->size?->value ?? '')),
                'current_qty' => $currentQty,
                'minimum_stock' => $threshold,
            ]
        );
    }

    public function notifyLowStockForComponent(Component $component): void
    {
        $component->loadMissing(['branch.setting']);

        $threshold = (int) ($component->branch?->setting?->minimum_stock ?? 0);
        if ($threshold < 0) {
            $threshold = 0;
        }

        $currentQty = (int) DB::table('component_stock_movements')
            ->where('component_id', $component->id)
            ->sum('qty');

        if ($currentQty > $threshold) {
            return;
        }

        $stockKey = 'component:' . $component->id;
        if ($this->hasRecentStockAlert((int) $component->branch_id, $stockKey)) {
            return;
        }

        $this->createForBranchAdmins(
            (int) $component->branch_id,
            'stock_below_minimum',
            'Stok component mencapai minimum',
            sprintf(
                'Stok component %s saat ini %d dan sudah menyentuh batas minimum cabang (%d).',
                trim(($component->name ?? 'Component') . ' ' . ($component->type_size ?? '')),
                $currentQty,
                $threshold
            ),
            [
                'stock_key' => $stockKey,
                'item_type' => 'component',
                'item_id' => $component->id,
                'item_name' => trim(($component->name ?? 'Component') . ' ' . ($component->type_size ?? '')),
                'current_qty' => $currentQty,
                'minimum_stock' => $threshold,
            ]
        );
    }

    public function createForBranchAdmins(
        int $branchId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $recipients = $this->resolveRecipientsForBranch($branchId);
        $this->createForUsers($recipients, $branchId, $type, $title, $body, $data);
    }

    public function createForUsers(
        Collection $users,
        ?int $branchId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $now = now();
        $rows = $users
            ->unique('id')
            ->map(fn (User $user) => [
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            UserNotification::insert($rows);
            $this->fcm->sendToUsers($users, $title, $body, [
                ...$data,
                'notification_type' => $type,
                'branch_id' => $branchId,
            ]);
        }
    }

    private function resolveRecipientsForBranch(int $branchId): Collection
    {
        return User::query()
            ->with('role')
            ->whereNull('deleted_at')
            ->whereHas('role', function ($query) {
                $query->whereIn('name', ['administrator', 'admin pusat', 'admin cabang']);
            })
            ->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('role', function ($roleQuery) {
                        $roleQuery->whereIn('name', ['administrator', 'admin pusat']);
                    });
            })
            ->get();
    }

    private function hasRecentStockAlert(int $branchId, string $stockKey): bool
    {
        return UserNotification::query()
            ->where('branch_id', $branchId)
            ->where('type', 'stock_below_minimum')
            ->where('created_at', '>=', now()->subHours(12))
            ->whereJsonContains('data->stock_key', $stockKey)
            ->exists();
    }
}
