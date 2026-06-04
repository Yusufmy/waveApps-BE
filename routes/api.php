<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\FCMController;

Route::middleware('throttle:5,1')->group(function () {

    Route::post(
        '/register',
        [AuthController::class, 'register']
    );

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );
});

Route::middleware(['jwt.auth'])->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);

    Route::patch('/profile', [ProfileController::class, 'update']);

    Route::post(
        '/profile/photo',
        [ProfileController::class, 'uploadPhoto']
    );

    Route::get('/users', [
        UserController::class,
        'index'
    ]);

    Route::post(
        '/conversations',
        [ConversationController::class, 'create']
    );

    Route::get(
        '/conversations',
        [ConversationController::class, 'index']
    );

    Route::post(
        '/messages',
        [MessageController::class, 'send']
    );

    Route::post(
        '/messages-delivered',
        [MessageController::class, 'delivered']
    );

    Route::get(
        '/messages/{conversationId}',
        [MessageController::class, 'list']
    );

    Route::post(
        '/read/{conversationId}',
        [MessageController::class, 'markAsRead']
    );

    Route::post(
        '/stories',
        [StoryController::class, 'store']
    );
    Route::get(
        '/stories',
        [StoryController::class, 'index']
    );
    Route::get(
        '/stories/{id}',
        [StoryController::class, 'show']
    );

    Route::post('/calls/start', [
        CallController::class,
        'start'
    ]);

    Route::post('/calls/{id}/accept', [
        CallController::class,
        'accept'
    ]);

    Route::post('/calls/{id}/reject', [
        CallController::class,
        'reject'
    ]);

    Route::post('/calls/{id}/end', [
        CallController::class,
        'end'
    ]);

    Route::get('/calls/history', [
        CallController::class,
        'history'
    ]);

    Route::post(
        '/save-fcm-token',
        [FCMController::class, 'saveFcmToken']
    );

    Route::get(
        '/test-notification/{userId}',
        [FCMController::class, 'testNotification']
    );
});
