<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * Membuat room chat baru
     */
    public function create(Request $request)
    {
        try {

            $request->validate([
                'user_id' => 'required|exists:users,id'
            ], [
                'user_id.required' => 'User tujuan wajib dipilih',
                'user_id.exists' => 'User tidak ditemukan',
            ]);

            $authUser = JWTAuth::parseToken()->authenticate();

            if ($authUser->id == $request->user_id) {

                return response()->json([
                    'status' => false,
                    'message' => 'Tidak bisa membuat chat dengan diri sendiri'
                ], 422);
            }

            // cek apakah conversation sudah ada
            $conversation = Conversation::whereHas(
                'participants',
                function ($query) use ($authUser) {
                    $query->where('user_id', $authUser->id);
                }
            )->whereHas(
                'participants',
                function ($query) use ($request) {
                    $query->where('user_id', $request->user_id);
                }
            )->first();

            if ($conversation) {

                return response()->json([
                    'status' => true,
                    'message' => 'Conversation ditemukan',
                    'data' => $conversation
                ]);
            }

            // buat conversation baru
            $conversation = Conversation::create([
                'type' => 'private'
            ]);

            ConversationParticipant::insert([
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $authUser->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $request->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Conversation berhasil dibuat',
                'data' => $conversation
            ]);
        } catch (ValidationException $e) {

            return response()->json([
                'status' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List conversation user login
     */
    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $conversations = $user
            ->conversations()
            ->with('participants')
            ->whereNotNull('last_message')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $conversations
        ]);
    }
}
