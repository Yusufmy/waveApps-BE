<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;


class FCMController extends Controller
{
    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required'
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        $user->update([
            'fcm_token' => $request->fcm_token
        ]);

        return response()->json([
            'status' => true
        ]);
    }

    /**
     * TEST PUSH NOTIFICATION
     */
    public function testNotification($userId)
    {
        $user = User::findOrFail($userId);

        if (!$user->fcm_token) {

            return response()->json([
                'status' => false,
                'message' => 'FCM token kosong'
            ]);
        }

        $messaging = app('firebase.messaging');

        $message = CloudMessage::withTarget(
            'token',
            $user->fcm_token
        )
            ->withNotification(
                Notification::create(
                    'Test Notification',
                    'Halo ini notif dari Laravel 🚀'
                )
            )
            ->withData([
                'type' => 'chat',
                'conversation_id' => '1'
            ]);

        $messaging->send($message);

        return response()->json([
            'status' => true,
            'message' => 'Notif berhasil dikirim'
        ]);
    }
}
