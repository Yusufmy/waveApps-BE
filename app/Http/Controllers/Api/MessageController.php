<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Message;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    /**
     * Kirim pesan
     */
    public function send(Request $request)
    {
        try {

            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'message' => 'required|string|max:5000',
            ], [
                'conversation_id.required' => 'Conversation wajib dipilih',
                'conversation_id.exists' => 'Conversation tidak ditemukan',

                'message.required' => 'Pesan wajib diisi',
                'message.max' => 'Pesan terlalu panjang',
            ]);

            $user = JWTAuth::parseToken()->authenticate();

            $conversation = $user
                ->conversations()
                ->where('conversations.id', $request->conversation_id)
                ->first();

            if (!$conversation) {

                return response()->json([
                    'status' => false,
                    'message' => 'Anda bukan peserta conversation ini'
                ], 403);
            }

            $message = Message::create([
                'conversation_id' => $request->conversation_id,
                'sender_id' => $user->id,
                'message' => trim($request->message),
                'message_type' => 'text',
            ]);

            $conversation->update([
                'last_message'    => $message->message,
                'last_message_at' => now(),
                'last_sender_id'  => $user->id,
            ]);

            // Kirim ke Firebase Realtime Database
            $database = app('firebase.database');

            $firebaseMessage = $database
                ->getReference(
                    "messages/{$request->conversation_id}"
                )
                ->push([
                    'message_id'      => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id'       => $message->sender_id,
                    'sender_name'     => $user->name,
                    'message'         => $message->message,
                    'message_type'    => 'text',
                    'status'          => 'sent',
                    'created_at'      => now()->timestamp,
                ]);

            // $message->update([
            //     'firebase_key' => $firebaseMessage->getKey()
            // ]);

            return response()->json([
                'status' => true,
                'message' => 'Pesan berhasil dikirim',
                'data' => $message
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
     * Ambil daftar pesan
     */
    public function list($conversationId)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $conversation = $user
            ->conversations()
            ->where('conversations.id', $conversationId)
            ->first();

        if (!$conversation) {

            return response()->json([
                'status' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $messages = Message::with('sender')
            ->where(
                'conversation_id',
                $conversationId
            )
            ->orderBy('id', 'desc')
            ->paginate(50);

        return response()->json([
            'status' => true,
            'data' => $messages
        ]);
    }
}
