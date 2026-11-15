<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Changelog\ChangelogEntryController;
use Illuminate\Support\Facades\Route;

/*
| Release notes. Reading needs `changelog.view` (every role); writing needs
| `changelog.manage`. Saving never shows anything to readers: publishing (or
| a schedule coming due) does.
*/
$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('changelog')->as('changelog.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:changelog.view'])->group(function () {
        Route::get('entries', [ChangelogEntryController::class, 'index'])->name('entries.index');
        Route::get('entries/{entry}', [ChangelogEntryController::class, 'show'])->whereUuid('entry')->name('entries.show');
    });

    Route::middleware([...$base, 'super-admin.can:changelog.manage'])->group(function () {
        Route::post('preview', [ChangelogEntryController::class, 'preview'])->name('preview');

        Route::post('entries', [ChangelogEntryController::class, 'store'])->name('entries.store');
        Route::patch('entries/{entry}', [ChangelogEntryController::class, 'update'])->whereUuid('entry')->name('entries.update');
        Route::delete('entries/{entry}', [ChangelogEntryController::class, 'destroy'])->whereUuid('entry')->name('entries.destroy');
        Route::post('entries/{entry}/publish', [ChangelogEntryController::class, 'publish'])->whereUuid('entry')->name('entries.publish');
        Route::post('entries/{entry}/schedule', [ChangelogEntryController::class, 'schedule'])->whereUuid('entry')->name('entries.schedule');
        Route::post('entries/{entry}/unpublish', [ChangelogEntryController::class, 'unpublish'])->whereUuid('entry')->name('entries.unpublish');
        Route::post('entries/{entry}/archive', [ChangelogEntryController::class, 'archive'])->whereUuid('entry')->name('entries.archive');
    });
});
