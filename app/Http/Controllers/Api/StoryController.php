<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryView;
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

        /**
         * FIREBASE REALTIME
         */
        $database = app('firebase.database');

        $database
            ->getReference("story_users/{$user->id}")
            ->set([
                'user_id' => $user->id,
                'has_story' => true,
                'last_story_id' => $story->id,
                'updated_at' => now()->timestamp,
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
            ->map(function ($stories) {
                return [
                    'user' => $stories->first()->user,
                    'stories' => $stories->values(),
                ];
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $stories,
        ]);
    }

    public function show($userId)
    {
        $stories = Story::with('user')
            ->where('user_id', $userId)
            ->where('expired_at', '>', now())
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $stories,
        ]);
    }

    public function myStory()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $stories = Story::where(
            'user_id',
            $user->id
        )
            ->where(
                'expired_at',
                '>',
                now()
            )
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'has_story' => $stories->isNotEmpty(),
            'data' => $stories,
        ]);
    }

    public function destroy($id)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $story = Story::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $story->delete();

        $activeStoryCount = Story::where('user_id', $user->id)
            ->where('expired_at', '>', now())
            ->count();

        $database = app('firebase.database');

        if ($activeStoryCount <= 0) {

            $database
                ->getReference("story_users/{$user->id}")
                ->remove();
        } else {

            $database
                ->getReference("story_users/{$user->id}")
                ->update([
                    'user_id' => $user->id,
                    'has_story' => true,
                    'story_count' => $activeStoryCount,
                    'updated_at' => now()->timestamp,
                ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Story berhasil dihapus',
            'has_story' => $activeStoryCount > 0,
            'story_count' => $activeStoryCount,
        ]);
    }

    public function markViewed(Request $request)
    {
        $request->validate([
            'story_id' => 'required|exists:stories,id'
        ]);

        $user = JWTAuth::parseToken()->authenticate();

        StoryView::firstOrCreate([
            'story_id' => $request->story_id,
            'viewer_id' => $user->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Story viewed'
        ]);
    }

    public function viewers($id)
    {
        $story = Story::findOrFail($id);

        $viewers = StoryView::with('viewer')
            ->where('story_id', $id)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'count' => $viewers->count(),
            'data' => $viewers
        ]);
    }
}
