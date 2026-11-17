<?php

use App\Http\Controllers\Api\Internal\V1\Notifications\AccountNotificationPreferenceController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationEventController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationUnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('notifications')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('summary', [NotificationController::class, 'summary']);
    Route::get('events', [NotificationEventController::class, 'index']);
    Route::get('catalog', [NotificationEventController::class, 'catalog']);
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('read', [NotificationController::class, 'markManyRead']);
    Route::post('archive', [NotificationController::class, 'archive']);
    Route::post('unarchive', [NotificationController::class, 'unarchive']);
    Route::get('preferences', [AccountNotificationPreferenceController::class, 'show']);
    Route::put('preferences', [AccountNotificationPreferenceController::class, 'update']);
    Route::post('{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification');
    Route::delete('{notification}', [NotificationController::class, 'destroy'])->whereUuid('notification');
});

// RFC 8058 one-click unsubscribe from one event's email, POSTed by the mail
// client from the `List-Unsubscribe` header: no session, so the URL
// signature is the authentication.
Route::post('notifications/unsubscribe/{user}', NotificationUnsubscribeController::class)
    ->middleware('signed')
    ->name('notifications.unsubscribe.one-click');
