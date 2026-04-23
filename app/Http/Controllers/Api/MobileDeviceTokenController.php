<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\Request;

class MobileDeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'platform' => ['nullable', 'string', 'max:32'],
            'provider' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $deviceToken = MobileDeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $validated['token'],
            ],
            [
                'branch_id' => $user->branch_id,
                'platform' => $validated['platform'] ?? null,
                'provider' => $validated['provider'] ?? 'fcm',
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Token device berhasil disimpan.',
            'data' => $deviceToken,
        ], 201);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
        ]);

        MobileDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->update([
                'is_active' => false,
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'message' => 'Token device berhasil dinonaktifkan.',
        ]);
    }
}
