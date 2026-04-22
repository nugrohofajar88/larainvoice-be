<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchBankAccount;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::with(['bankAccounts', 'invoiceCounter', 'setting']);
        if (!$request->user()) return response()->json(['message' => 'Tidak terautentikasi'], 401);
        if (!$request->user()->isSuperAdmin()) {
            // non-superadmin hanya boleh melihat branch mereka sendiri
            $query->where('id', $request->user()->branch_id);
        }

        // =========================
        // 🔍 GLOBAL SEARCH
        // =========================
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%")
                  ->orWhere('address', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('website', 'like', "%{$s}%");
            });
        }

        // =========================
        // 🔍 COLUMN FILTERS
        // =========================
        $filters = $request->only(['name', 'city', 'address', 'phone', 'email', 'website']);
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, 'like', "%{$value}%");
            }
        }

        $perPage = min($request->input('per_page', 15), 100);
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc'));
        $allowed = ['id','name','city','address','phone','email','website','created_at'];
        if (!in_array($sortBy, $allowed)) $sortBy = 'id';
        if (!in_array($sortDir, ['asc','desc'])) $sortDir = 'desc';

        return response()->json($query->orderBy($sortBy, $sortDir)->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()) return response()->json(['message' => 'Tidak terautentikasi'], 401);
        if (!$request->user()->isSuperAdmin() && $id != $request->user()->branch_id) return response()->json(['message' => 'Akses ditolak'], 403);

        $item = Branch::with(['bankAccounts', 'invoiceCounter', 'setting'])->find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        return response()->json($item);
    }

    public function store(Request $request)
    {
        if (!$request->user()) return response()->json(['message' => 'Tidak terautentikasi'], 401);
        if (!$request->user()->isSuperAdmin()) return response()->json(['message' => 'Akses ditolak'], 403);
        // normalize empty bank account ids from empty string to null (forms send empty string for empty inputs)
        $input = $request->all();
        if (isset($input['bank_accounts']) && is_array($input['bank_accounts'])) {
            foreach ($input['bank_accounts'] as $i => $ba) {
                if (array_key_exists('id', $ba) && $ba['id'] === '') {
                    $input['bank_accounts'][$i]['id'] = null;
                }
            }
            $request->merge($input);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:branches,name',
            'city' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|string',

            // nested
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.id' => 'nullable|exists:branch_bank_accounts,id',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_holder' => 'required_with:bank_accounts|string',
            'bank_accounts.*.bank_code' => 'nullable|string',
            'bank_accounts.*.is_default' => 'nullable|boolean',

            'setting' => 'nullable|array',
            'setting.minimum_stock' => 'nullable|integer',
            'setting.sales_commission_percentage' => 'nullable|numeric',
            'setting.invoice_header_name' => 'nullable|string',
            'setting.invoice_header_position' => 'nullable|string',
            'setting.invoice_footer_note' => 'nullable|string',
        ]);

        $branch = DB::transaction(function () use ($validated) {
            $branchData = Arr::only($validated, ['name','city','address','phone','email','website']);
            // ensure required DB columns that are non-nullable have defaults
            if (!array_key_exists('city', $branchData)) {
                $branchData['city'] = '';
            }
            $branch = Branch::create($branchData);

            // setting
            if (!empty($validated['setting']) && is_array($validated['setting'])) {
                $settingData = $validated['setting'];
                BranchSetting::updateOrCreate(
                    ['branch_id' => $branch->id],
                    Arr::only($settingData, ['minimum_stock','sales_commission_percentage','invoice_header_name','invoice_header_position','invoice_footer_note'])
                );
            }

            // bank accounts upsert + sync
            if (isset($validated['bank_accounts']) && is_array($validated['bank_accounts'])) {
                $incomingIds = [];
                foreach ($validated['bank_accounts'] as $ba) {
                    if (!empty($ba['id'])) {
                        // if id provided but doesn't belong to branch, ignore
                        $acc = BranchBankAccount::where('id', $ba['id'])->first();
                        if ($acc) {
                            $acc->update(Arr::only($ba, ['bank_name','account_number','account_holder','bank_code','is_default']));
                            $incomingIds[] = $acc->id;
                        }
                    } else {
                        $new = $branch->bankAccounts()->create(Arr::only($ba, ['bank_name','account_number','account_holder','bank_code','is_default']));
                        $incomingIds[] = $new->id;
                    }
                }

                // delete accounts not in payload
                if (!empty($incomingIds)) {
                    $branch->bankAccounts()->whereNotIn('id', $incomingIds)->delete();
                }

                // ensure single default
                $defaults = $branch->bankAccounts()->where('is_default', true)->pluck('id')->toArray();
                if (count($defaults) > 1) {
                    $keep = array_shift($defaults);
                    $branch->bankAccounts()->whereIn('id', $defaults)->update(['is_default' => false]);
                }
            }

            return $branch;
        });

        return response()->json(['message' => 'Branch dibuat', 'data' => $branch->load(['bankAccounts','setting','invoiceCounter'])], 201);
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::find($id);
        if (!$branch) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        // normalize empty bank account ids
        $input = $request->all();
        if (!$request->user()) return response()->json(['message' => 'Tidak terautentikasi'], 401);
        if (!$request->user()->isSuperAdmin() && $branch->id != $request->user()->branch_id) return response()->json(['message' => 'Akses ditolak'], 403);
        if (isset($input['bank_accounts']) && is_array($input['bank_accounts'])) {
            foreach ($input['bank_accounts'] as $i => $ba) {
                if (array_key_exists('id', $ba) && $ba['id'] === '') {
                    $input['bank_accounts'][$i]['id'] = null;
                }
            }
            $request->merge($input);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|unique:branches,name,' . $id,
            'city' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|string',

            // nested
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.id' => 'nullable|exists:branch_bank_accounts,id',
            'bank_accounts.*.bank_name' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_number' => 'required_with:bank_accounts|string',
            'bank_accounts.*.account_holder' => 'required_with:bank_accounts|string',
            'bank_accounts.*.bank_code' => 'nullable|string',
            'bank_accounts.*.is_default' => 'nullable|boolean',

            'setting' => 'nullable|array',
            'setting.minimum_stock' => 'nullable|integer',
            'setting.sales_commission_percentage' => 'nullable|numeric',
            'setting.invoice_header_name' => 'nullable|string',
            'setting.invoice_header_position' => 'nullable|string',
            'setting.invoice_footer_note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $branch) {
            $branchData = Arr::only($validated, ['name','city','address','phone','email','website']);
            foreach ($branchData as $k => $v) $branch->{$k} = $v;
            $branch->save();

            // setting
            if (isset($validated['setting']) && is_array($validated['setting'])) {
                $settingData = $validated['setting'];
                BranchSetting::updateOrCreate(['branch_id' => $branch->id], Arr::only($settingData, ['minimum_stock','sales_commission_percentage','invoice_header_name','invoice_header_position','invoice_footer_note']));
            }

            // bank accounts upsert + sync
            if (isset($validated['bank_accounts']) && is_array($validated['bank_accounts'])) {
                $incomingIds = [];
                foreach ($validated['bank_accounts'] as $ba) {
                    if (!empty($ba['id'])) {
                        $acc = BranchBankAccount::where('id', $ba['id'])->where('branch_id', $branch->id)->first();
                        if ($acc) {
                            $acc->update(Arr::only($ba, ['bank_name','account_number','account_holder','bank_code','is_default']));
                            $incomingIds[] = $acc->id;
                        }
                    } else {
                        $new = $branch->bankAccounts()->create(Arr::only($ba, ['bank_name','account_number','account_holder','bank_code','is_default']));
                        $incomingIds[] = $new->id;
                    }
                }

                // delete accounts not in payload
                $branch->bankAccounts()->whereNotIn('id', $incomingIds)->delete();

                // ensure single default
                $defaults = $branch->bankAccounts()->where('is_default', true)->pluck('id')->toArray();
                if (count($defaults) > 1) {
                    $keep = array_shift($defaults);
                    $branch->bankAccounts()->whereIn('id', $defaults)->update(['is_default' => false]);
                }
            }
        });

        return response()->json(['message' => 'Branch diupdate', 'data' => $branch->load(['bankAccounts','setting','invoiceCounter'])]);
    }

    public function destroy(Request $request, $id)
    {
        $item = Branch::find($id);
        if (!$item) return response()->json(['message' => 'Data tidak ditemukan'], 404);
        if (!$request->user()) return response()->json(['message' => 'Tidak terautentikasi'], 401);
        if (!$request->user()->isSuperAdmin() && $item->id != $request->user()->branch_id) return response()->json(['message' => 'Akses ditolak'], 403);
        // record who deleted
        if ($request->user()) {
            $item->deleted_by = $request->user()->id;
            $item->save();
        }
        $item->delete();
        return response()->json(['message' => 'Branch dihapus']);
    }
}
