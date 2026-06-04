<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Message;
use App\Models\ConversationParticipant;
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

            // simpan pesan
            $firebaseMessage = $database
                ->getReference(
                    "messages/{$request->conversation_id}"
                )
                ->push([
                    'message_id'      => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id'       => $message->sender_id,
                    'sender_name'     => $user->name,
                    'photo'           => $user->photo,
                    'message'         => $message->message,
                    'message_type'    => 'text',
                    'status'          => 'sent',
                    'created_at'      => now()->timestamp,
                ]);

            // update room meta
            $database
                ->getReference(
                    "rooms/{$request->conversation_id}/meta"
                )
                ->update([
                    'last_message'    => $message->message,
                    'last_message_at' => now()->toDateTimeString(),
                ]);

            $participants = ConversationParticipant::where(
                'conversation_id',
                $request->conversation_id
            )
                ->where('user_id', '!=', $user->id)
                ->pluck('user_id');

            foreach ($participants as $participantId) {

                $ref = $database->getReference(
                    "rooms/{$request->conversation_id}/participants/{$participantId}/unread_count"
                );

                $current = $ref->getValue() ?? 0;

                $ref->set($current + 1);
            }

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

    public function delivered(Request $request)
    {
        try {

            $request->validate([
                'message_ids' => 'required|array',
                'message_ids.*' => 'exists:messages,id',
            ]);

            $database = app('firebase.database');

            $messages = Message::whereIn(
                'id',
                $request->message_ids
            )
                ->where('status', 'sent')
                ->get();

            foreach ($messages as $message) {

                $message->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

                $snapshot = $database
                    ->getReference(
                        "messages/{$message->conversation_id}"
                    )
                    ->orderByChild('message_id')
                    ->equalTo($message->id)
                    ->getSnapshot();

                foreach ($snapshot->getValue() ?? [] as $firebaseKey => $value) {

                    $database
                        ->getReference(
                            "messages/{$message->conversation_id}/{$firebaseKey}"
                        )
                        ->update([
                            'status' => 'delivered',
                            'delivered_at' => now()->timestamp,
                        ]);
                }
            }

            return response()->json([
                'status' => true,
            ]);
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

    public function markAsRead($conversationId)
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $database = app('firebase.database');

            $messages = Message::where(
                'conversation_id',
                $conversationId
            )
                ->where('sender_id', '!=', $user->id)
                ->where('status', '!=', 'read')
                ->get();

            foreach ($messages as $message) {

                $message->update([
                    'status' => 'read',
                    'read_at' => now(),
                ]);

                $snapshot = $database
                    ->getReference(
                        "messages/{$conversationId}"
                    )
                    ->orderByChild('message_id')
                    ->equalTo($message->id)
                    ->getSnapshot();

                foreach ($snapshot->getValue() ?? [] as $firebaseKey => $value) {

                    $database
                        ->getReference(
                            "messages/{$conversationId}/{$firebaseKey}"
                        )
                        ->update([
                            'status' => 'read',
                            'read_at' => now()->timestamp,
                        ]);
                }
            }

            // reset unread badge
            $database
                ->getReference(
                    "rooms/{$conversationId}/participants/{$user->id}/unread_count"
                )
                ->set(0);

            return response()->json([
                'status' => true,
                'message' => 'Pesan berhasil dibaca'
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
