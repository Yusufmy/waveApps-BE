<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Conversation;



class UserController extends Controller
{
    public function index(Request $request)
    {
        $authUser = JWTAuth::parseToken()->authenticate();

        /**
         * Ambil semua user yang sudah ada conversation
         */
        $conversationUserIds = Conversation::query()
            ->whereHas('participants', function ($q) use ($authUser) {
                $q->where('users.id', $authUser->id);
            })
            ->where(function ($q) use ($authUser) {

                $q->where('created_by', $authUser->id)

                    ->orWhereHas('messages');
            })
            ->with('participants:id')
            ->get()
            ->pluck('participants')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->filter(fn($id) => $id != $authUser->id)
            ->values()
            ->toArray();

        $users = User::query()

            ->when(
                $request->search,
                function ($query) use ($request) {
                    $query->where(
                        'name',
                        'like',
                        "%{$request->search}%"
                    );
                }
            )

            ->where('id', '!=', $authUser->id)

            ->whereNotIn(
                'id',
                $conversationUserIds
            )

            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $users
        ]);
    }
}
