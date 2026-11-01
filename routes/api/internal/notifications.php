<?php

use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationEventController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('events', [NotificationEventController::class, 'index']);
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('{notification}/read', [NotificationController::class, 'markRead']);
    Route::delete('{notification}', [NotificationController::class, 'destroy']);
});
