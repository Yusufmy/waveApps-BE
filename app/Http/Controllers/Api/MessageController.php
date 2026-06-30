<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Message;
use App\Models\User;
use App\Models\ConversationParticipant;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    /**
     * Kirim pesan
     */
    private function getLastMessage($message)
    {
        switch ($message->message_type) {

            case 'text':
                return $message->message;

            case 'image':
                return "📷 Photo";

            case 'video':
                return "🎥 Video";

            case 'audio':
                return "🎤 Voice message";

            case 'file':
                return "📄 File";

            default:
                return "New message";
        }
    }

    public function send(Request $request)
    {

        try {


            Log::info("FIELDS", $request->all());

            Log::info("FILES", $request->allFiles());

            Log::info("HAS FILE", [
                'attachment_url' => $request->hasFile('attachment_url'),
                'attachment' => $request->hasFile('attachment'),
            ]);

            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'message_type' => 'required|in:text,image,video,audio,file',
                'message' => 'nullable|string|max:5000',
                'attachment_url' => 'nullable|file|max:20480',
                'duration' => 'nullable|integer',
                'file_name' => 'nullable|string|max:255',
                'file_size' => 'nullable|integer',
            ], [
                'conversation_id.required' => 'Conversation wajib dipilih',
                'conversation_id.exists' => 'Conversation tidak ditemukan',

                'message.required' => 'Pesan wajib diisi',
                'message.max' => 'Pesan terlalu panjang',
            ]);

            switch ($request->message_type) {

                case 'text':
                    if (empty(trim($request->message))) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Pesan wajib diisi'
                        ], 422);
                    }
                    break;

                case 'audio':
                case 'image':
                case 'video':
                case 'file':

                    if (empty($request->attachment_url)) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Attachment wajib diisi'
                        ], 422);
                    }

                    break;
            }

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

            $attachmentUrl = null;
            $fileName = null;
            $fileSize = null;

            if ($request->hasFile('attachment_url')) {

                $file = $request->file('attachment_url');

                $path = $file->store('chat', 'public');

                $attachmentUrl = $path;
                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
            }

            if ($request->hasFile('attachment_url')) {

                Log::info("FILE MASUK");

                $file = $request->file('attachment_url');

                $path = $file->store('chat', 'public');

                $attachmentUrl = $path;
                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();

                Log::info([
                    'path' => $path,
                    'name' => $fileName,
                    'size' => $fileSize,
                ]);
            }

            $message = Message::create([
                'conversation_id' => $request->conversation_id,
                'sender_id' => $user->id,
                'message' => trim($request->message ?? ''),
                'message_type' => $request->message_type,

                'attachment_url' => $attachmentUrl,
                'duration' => $request->duration,
                'file_name' => $fileName,
                'file_size' => $fileSize,
            ]);

            $conversation->update([
                'last_message'    => $this->getLastMessage($message),
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
                    'message_type'    => $message->message_type,
                    'attachment_url'  => $message->attachment_url,
                    'duration'        => $message->duration,
                    'file_name'       => $message->file_name,
                    'file_size'       => $message->file_size,
                    'status'          => 'sent',
                    'created_at'      => now()->timestamp,
                    'delivered_at' => null,
                    'read_at' => null,
                ]);

            // update room meta
            $database
                ->getReference(
                    "rooms/{$request->conversation_id}/meta"
                )
                ->update([
                    // 'last_message'    => $message->message,
                    'last_message'    => $this->getLastMessage($message),
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

                //kirim notification
                $receiver = User::find($participantId);

                if ($receiver && $receiver->fcm_token) {

                    $this->sendPushNotification(
                        $receiver->fcm_token,
                        $user->name,
                        // $message->message,
                        $this->getLastMessage($message),
                        [
                            'conversation_id' => (string) $request->conversation_id,
                            'sender_id'       => (string) $user->id,
                            'type'            => 'chat',
                        ]
                    );
                }
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

    private function sendPushNotification(
        string $token,
        string $title,
        string $body,
        array $data = []
    ) {
        $messaging = app('firebase.messaging');

        $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget(
            'token',
            $token
        )
            ->withNotification(
                \Kreait\Firebase\Messaging\Notification::create(
                    $title,
                    $body
                )
            )
            ->withData($data);

        $messaging->send($message);
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
