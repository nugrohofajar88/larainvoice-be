<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentFile;
use App\Services\MobileNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::query()
            ->with(['invoice.customer', 'invoice.branch', 'bankAccount', 'user', 'files'])
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->select('payments.*');

        if (!$user->isSuperAdmin()) {
            $query->where('payments.branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('payments.branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('payments.invoice_id', $request->integer('invoice_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('invoices.invoice_number', 'like', '%' . $search . '%')
                    ->orWhere('customers.full_name', 'like', '%' . $search . '%');
            });
        } elseif ($request->filled('invoice_number')) {
            $query->where('invoices.invoice_number', 'like', '%' . $request->input('invoice_number') . '%');
        }

        if ($request->filled('method')) {
            $query->where('payments.payment_method', 'like', '%' . $request->input('method') . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payments.payment_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payments.payment_date', '<=', $request->input('date_to'));
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowedSorts = ['id', 'invoice_number', 'amount', 'method', 'is_dp', 'payment_type', 'date', 'created_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        if ($sortBy === 'invoice_number') {
            $query->orderBy('invoices.invoice_number', $sortDir);
        } elseif ($sortBy === 'method') {
            $query->orderBy('payments.payment_method', $sortDir);
        } elseif ($sortBy === 'date') {
            $query->orderBy('payments.payment_date', $sortDir);
        } else {
            $query->orderBy('payments.' . $sortBy, $sortDir);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $result = $query->paginate($perPage);
        $result->getCollection()->transform(fn (Payment $payment) => $this->transform($payment));

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'branch_id' => $user->isSuperAdmin() ? ['nullable', 'exists:branches,id'] : ['nullable'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'in:dp,cicilan,pelunasan,refund'],
            'is_dp' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'bank_account_id' => ['nullable', 'exists:branch_bank_accounts,id'],
            'proof_image' => ['nullable', 'string'],
            'proof_files' => ['nullable', 'array', 'max:5'],
            'proof_files.*' => ['file', 'max:20480', 'mimes:pdf,png,jpg,jpeg'],
            'note' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::with('payments')->findOrFail($validated['invoice_id']);

        if (!$user->isSuperAdmin() && $invoice->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $branchId = $user->isSuperAdmin()
            ? ($validated['branch_id'] ?? $invoice->branch_id)
            : $user->branch_id;

        $alreadyPaid = (float) $invoice->payments->sum('amount');
        $remaining = max((float) $invoice->grand_total - $alreadyPaid, 0);
        $amount = (float) $validated['amount'];

        if ($remaining > 0 && $amount > $remaining) {
            return response()->json([
                'message' => 'Jumlah pembayaran melebihi sisa tagihan.',
            ], 422);
        }

        $payment = DB::transaction(function () use ($request, $invoice, $validated, $branchId, $amount, $remaining) {
            $paymentType = $validated['payment_type']
                ?? ((bool) ($validated['is_dp'] ?? ($amount < $remaining)) ? 'cicilan' : 'pelunasan');

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'branch_id' => $branchId,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'user_id' => $request->user()->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'] ?? $validated['method'] ?? 'Cash',
                'payment_type' => $paymentType,
                'is_dp' => in_array($paymentType, ['dp', 'cicilan'], true),
                'payment_date' => $validated['payment_date'] ?? $validated['date'] ?? now()->toDateString(),
                'proof_image' => $validated['proof_image'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            $firstStoredPath = null;

            foreach ($request->file('proof_files', []) as $file) {
                $storedPath = $file->store("payment-proofs/{$payment->id}", 'public');
                $firstStoredPath ??= $storedPath;

                PaymentFile::create([
                    'payment_id' => $payment->id,
                    'file_path' => $storedPath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_extension' => strtolower((string) $file->getClientOriginalExtension()),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }

            if ($firstStoredPath && empty($payment->proof_image)) {
                $payment->update(['proof_image' => $firstStoredPath]);
            }

            return $payment;
        });

        app(MobileNotificationService::class)->notifyPaymentCreated($payment);

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat',
            'data' => $this->transform($payment->load(['invoice.customer', 'invoice.branch', 'bankAccount', 'user', 'files'])),
        ], 201);
    }

    public function downloadFile(Request $request, $paymentId, $fileId)
    {
        $user = $request->user();
        $payment = Payment::with('files', 'invoice')->find($paymentId);

        if (!$payment) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan'], 404);
        }

        if (!$user->isSuperAdmin() && $payment->branch_id !== $user->branch_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $file = $payment->files->firstWhere('id', (int) $fileId);

        if (!$file) {
            return response()->json(['message' => 'File bukti bayar tidak ditemukan'], 404);
        }

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json(['message' => 'Berkas fisik tidak ditemukan'], 404);
        }

        return Storage::disk('public')->download(
            $file->file_path,
            $file->file_name ?: basename($file->file_path)
        );
    }

    private function transform(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => $payment->invoice?->invoice_number,
            'customer_name' => $payment->invoice?->customer?->full_name,
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
            'payment_type' => $payment->payment_type ?: ($payment->is_dp ? 'cicilan' : 'pelunasan'),
            'is_dp' => (bool) $payment->is_dp,
            'date' => optional($payment->payment_date)->format('Y-m-d'),
            'user_id' => $payment->user_id,
            'handled_by' => $payment->user?->name,
            'note' => $payment->note ?: $payment->bankAccount?->bank_name,
            'files' => $payment->files->map(fn (PaymentFile $file) => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_extension' => $file->file_extension,
                'file_size' => (int) ($file->file_size ?? 0),
                'mime_type' => $file->mime_type,
            ])->values()->all(),
        ];
    }
}
