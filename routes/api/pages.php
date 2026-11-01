<?php

use App\Http\Controllers\Pages\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workflow pages at their public link
|--------------------------------------------------------------------------
|
| Unauthenticated: the `{token}` is the page's link, and PublicPageController
| decides what it may answer. Kept out of routes/api/{internal,public}/ so
| none of their auth middleware applies.
|
*/

Route::prefix('v1/p/{token}')->name('public-pages.')->group(function (): void {
    Route::get('/', [PublicPageController::class, 'show'])->middleware('throttle:page-reads')->name('show');
    Route::get('meta', [PublicPageController::class, 'meta'])->middleware('throttle:page-reads')->name('meta');
    Route::post('events', [PublicPageController::class, 'event'])->middleware('throttle:page-reads')->name('events');
    Route::post('unlock', [PublicPageController::class, 'unlock'])->middleware('throttle:page-unlock')->name('unlock');
    Route::post('submissions', [PublicPageController::class, 'submit'])->middleware('throttle:page-submissions')->name('submissions.store');
    Route::get('submissions/{submission}', [PublicPageController::class, 'result'])->middleware('throttle:page-reads')->name('submissions.show');
});
