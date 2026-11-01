<?php

use App\Http\Controllers\Api\Internal\V1\Broadcasts\AnnouncementController;
use App\Http\Controllers\Api\Internal\V1\Broadcasts\CommunicationPreferenceController;
use App\Http\Controllers\Api\Internal\V1\Broadcasts\OneClickUnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');

    Route::get('communication-preferences', [CommunicationPreferenceController::class, 'show'])->name('communication-preferences.show');
    Route::patch('communication-preferences', [CommunicationPreferenceController::class, 'update'])->name('communication-preferences.update');
});

// RFC 8058 one-click unsubscribe, POSTed by the mail client from the
// `List-Unsubscribe` header: no session, so the URL signature is the authentication.
Route::post('broadcasts/unsubscribe/{user}', OneClickUnsubscribeController::class)
    ->middleware('signed')
    ->name('broadcasts.unsubscribe.one-click');
