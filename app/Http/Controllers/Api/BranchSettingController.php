<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\BranchSetting;

class BranchSettingController extends Controller
{
    public function show($branchId)
    {
        $setting = BranchSetting::where('branch_id', $branchId)->first();

        if (!$setting) {
            return response()->json([
                'message' => 'Setting tidak ditemukan'
            ], 404);
        }

        return response()->json($setting);
    }

    public function update(Request $request, $branchId)
    {
        $setting = BranchSetting::where('branch_id', $branchId)->first();

        if (!$setting) {
            return response()->json([
                'message' => 'Setting tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'minimum_stock' => 'nullable|integer',
            'sales_commission_percentage' => 'nullable|numeric',
            'invoice_header_name' => 'nullable|string',
            'invoice_header_position' => 'nullable|string',
            'invoice_footer_note' => 'nullable|string',
        ]);

        $setting->update(Arr::only($validated, [
            'minimum_stock',
            'sales_commission_percentage',
            'invoice_header_name',
            'invoice_header_position',
            'invoice_footer_note',
        ]));

        return response()->json([
            'message' => 'Berhasil update setting',
            'data' => $setting
        ]);
    }
}