<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts\AnnouncementController;
use App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts\BroadcastAudienceController;
use App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts\BroadcastCampaignActionController;
use App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts\BroadcastCampaignController;
use App\Http\Controllers\Api\SuperAdmin\V1\Broadcasts\BroadcastRecipientController;
use Illuminate\Support\Facades\Route;

/*
| Telling members things: in-app announcements (banners and maintenance
| notices) and email campaigns.
|
| Reading needs `broadcasts.view` (every role); writing needs
| `broadcasts.manage`. Starting or scheduling a campaign also needs a fresh
| authenticator code and the previewed recipient count (see the form
| requests), and is made safe to retry with an `Idempotency-Key`.
*/
$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('broadcasts')->as('broadcasts.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:broadcasts.view'])->group(function () {
        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->whereUuid('announcement')->name('announcements.show');

        Route::get('campaigns', [BroadcastCampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaigns/{campaign}', [BroadcastCampaignController::class, 'show'])->whereUuid('campaign')->name('campaigns.show');
        Route::get('campaigns/{campaign}/recipients', [BroadcastRecipientController::class, 'index'])->whereUuid('campaign')->name('campaigns.recipients');
    });

    Route::middleware([...$base, 'super-admin.can:broadcasts.manage'])->group(function () {
        Route::post('audience/preview', [BroadcastAudienceController::class, 'store'])->name('audience.preview');

        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('announcements/{announcement}', [AnnouncementController::class, 'update'])->whereUuid('announcement')->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->whereUuid('announcement')->name('announcements.destroy');
        Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->whereUuid('announcement')->name('announcements.publish');
        Route::post('announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])->whereUuid('announcement')->name('announcements.unpublish');

        Route::post('campaigns', [BroadcastCampaignController::class, 'store'])->name('campaigns.store');
        Route::patch('campaigns/{campaign}', [BroadcastCampaignController::class, 'update'])->whereUuid('campaign')->name('campaigns.update');
        Route::delete('campaigns/{campaign}', [BroadcastCampaignController::class, 'destroy'])->whereUuid('campaign')->name('campaigns.destroy');

        Route::middleware('throttle:super-admin-sensitive')->group(function () {
            Route::post('campaigns/{campaign}/test-send', [BroadcastCampaignActionController::class, 'testSend'])->whereUuid('campaign')->name('campaigns.test-send');
            Route::post('campaigns/{campaign}/cancel', [BroadcastCampaignActionController::class, 'cancel'])->whereUuid('campaign')->name('campaigns.cancel');

            Route::middleware('super-admin.idempotent')->group(function () {
                Route::post('campaigns/{campaign}/start', [BroadcastCampaignActionController::class, 'start'])->whereUuid('campaign')->name('campaigns.start');
                Route::post('campaigns/{campaign}/schedule', [BroadcastCampaignActionController::class, 'schedule'])->whereUuid('campaign')->name('campaigns.schedule');
                Route::post('campaigns/{campaign}/retry-failed', [BroadcastCampaignActionController::class, 'retryFailed'])->whereUuid('campaign')->name('campaigns.retry-failed');
            });
        });
    });
});
