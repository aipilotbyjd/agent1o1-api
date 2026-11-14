<?php

use App\Http\Controllers\Api\Public\V1\Changelog\PublicChangelogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The public changelog
|--------------------------------------------------------------------------
|
| Unauthenticated, read-only, and limited to entries marked `public`. Kept out
| of routes/api/{internal,public}/ so neither their auth middleware nor the
| API-key requirement applies. The signed-in version lives at /v1/changelog.
|
*/

Route::prefix('v1/public/changelog')->middleware('throttle:changelog-reads')->name('public-changelog.')->group(function (): void {
    Route::get('/', [PublicChangelogController::class, 'index'])->name('index');
    Route::get('feed.rss', [PublicChangelogController::class, 'rss'])->name('rss');
    Route::get('feed.json', [PublicChangelogController::class, 'jsonFeed'])->name('json');
    Route::get('{slug}', [PublicChangelogController::class, 'show'])->name('show');
});
