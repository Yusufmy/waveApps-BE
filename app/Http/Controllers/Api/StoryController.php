<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Story;
use Tymon\JWTAuth\Facades\JWTAuth;

class StoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov|max:51200',
            'caption' => 'nullable|string|max:500'
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        $path = $request
            ->file('file')
            ->store(
                'stories',
                'public'
            );

        $story = Story::create([
            'user_id' => $user->id,

            'type' => str_contains(
                $request->file('file')->getMimeType(),
                'video'
            )
                ? 'video'
                : 'image',

            'media_url' => asset(
                'storage/' . $path
            ),

            'caption' => $request->caption,

            'expired_at' => now()->addHours(24)
        ]);

        return response()->json([
            'status' => true,
            'data' => $story
        ]);
    }

    public function index()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $participantIds = $user
            ->conversations()
            ->with('participants:id')
            ->get()
            ->pluck('participants')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->filter(fn($id) => $id != $user->id);

        $stories = Story::with('user')
            ->whereIn('user_id', $participantIds)
            ->where('expired_at', '>', now())
            ->latest()
            ->get()
            ->groupBy('user_id')
            ->values();

        return response()->json([
            'status' => true,
            'data' => $stories
        ]);
    }

    public function show($userId)
    {
        $stories = Story::where(
            'user_id',
            $userId
        )
            ->where(
                'expired_at',
                '>',
                now()
            )
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $stories
        ]);
    }
}
