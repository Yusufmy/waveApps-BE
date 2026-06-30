<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Call;
use App\Models\Conversation;

class CallController extends Controller
{
    /**
     * Membuat panggilan
     */
    public function start(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'receiver_id' => 'required|exists:users,id',
            'type' => 'required|in:voice,video',
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        $conversation = $user
            ->conversations()
            ->where('conversations.id', $request->conversation_id)
            ->first();

        if (!$conversation) {

            return response()->json([
                'status' => false,
                'message' => 'Conversation tidak ditemukan'
            ], 403);
        }

        $roomId = 'call_' . uniqid();

        $call = Call::create([
            'conversation_id' => $request->conversation_id,
            'caller_id' => $user->id,
            'receiver_id' => $request->receiver_id,
            'type' => $request->type,
            'status' => 'ringing',
            'room_id' => $roomId,
        ]);

        /**
         * FIREBASE REALTIME
         */
        $database = app('firebase.database');

        $database
            ->getReference(
                "calls/{$roomId}"
            )
            ->set([
                'call_id' => $call->id,
                'conversation_id' => $request->conversation_id,
                'caller_id' => $user->id,
                'caller_name' => $user->name,
                'caller_photo' => $user->photo,
                'receiver_id' => $request->receiver_id,
                'type' => $request->type,
                'status' => 'ringing',
                'room_id' => $roomId,
                'created_at' => now()->timestamp,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Panggilan dimulai',
            'data' => $call,
        ]);
    }

    /**
     * Accept call
     */
    public function accept($id)
    {
        $call = Call::findOrFail($id);

        $call->update([
            'status' => 'accepted',
            'started_at' => now(),
        ]);

        app('firebase.database')
            ->getReference("calls/{$call->room_id}")
            ->update([
                'status' => 'accepted'
            ]);

        return response()->json([
            'status' => true
        ]);
    }

    /**
     * End call
     */
    public function end($id)
    {
        $call = Call::findOrFail($id);

        $endedAt = now();

        $duration = $call->started_at
            ? $call->started_at->diffInSeconds($endedAt)
            : 0;

        $call->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration' => $duration,
        ]);

        app('firebase.database')
            ->getReference("calls/{$call->room_id}")
            ->update([
                'status' => 'ended'
            ]);

        return response()->json([
            'status' => true,
            'duration' => $duration,
        ]);
    }

    /**
     * Reject All call
     */
    public function reject($id)
    {
        $call = Call::findOrFail($id);

        $call->update([
            'status' => 'rejected',
            'ended_at' => now(),
        ]);

        app('firebase.database')
            ->getReference("calls/{$call->room_id}")
            ->update([
                'status' => 'rejected'
            ]);

        return response()->json([
            'status' => true
        ]);
    }
    /**
     * History Call
     */
    public function history()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $calls = Call::with([
            'caller',
            'receiver'
        ])
            ->where('caller_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $calls
        ]);
    }
}
