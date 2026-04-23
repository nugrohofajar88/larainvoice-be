<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseCloudMessagingService;
use App\Services\MobileNotificationService;
use Illuminate\Http\Request;

class NotificationTestController extends Controller
{
    public function diagnostics(FirebaseCloudMessagingService $fcm)
    {
        return response()->json([
            'configured' => $fcm->isConfigured(),
            'diagnostics' => $fcm->diagnostics(),
        ]);
    }

    public function send(Request $request, MobileNotificationService $notifications)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $title = $validated['title'] ?? 'Test push Pioneer CNC';
        $body = $validated['body'] ?? 'Jika notifikasi ini muncul, FCM Android sudah jalan realtime.';

        $notifications->createForUsers(
            collect([$user]),
            $user->branch_id,
            'test_push',
            $title,
            $body,
            [
                'test' => true,
                'sent_by_user_id' => $user->id,
            ]
        );

        return response()->json([
            'message' => 'Test push diproses.',
        ]);
    }
}
