<?php

use App\Http\Controllers\Api\Internal\V1\Changelog\ChangelogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('changelog')->name('changelog.')->group(function (): void {
    Route::get('/', [ChangelogController::class, 'index'])->name('index');
    Route::get('unread-count', [ChangelogController::class, 'unreadCount'])->name('unread-count');
    Route::get('latest-major', [ChangelogController::class, 'latestMajor'])->name('latest-major');
    Route::post('read-all', [ChangelogController::class, 'markAllRead'])->name('read-all');
    Route::get('{slug}', [ChangelogController::class, 'show'])->name('show');
    Route::post('{slug}/read', [ChangelogController::class, 'markRead'])->name('read');
});
