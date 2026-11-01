<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Customers\CustomerAnnotationController;
use App\Http\Controllers\Api\SuperAdmin\V1\Customers\CustomerOverviewController;
use App\Http\Controllers\Api\SuperAdmin\V1\Customers\CustomerSearchController;
use App\Http\Controllers\Api\SuperAdmin\V1\Customers\CustomerTimelineController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer pages: search, one-call overview, timeline, notes and tags
|--------------------------------------------------------------------------
|
| Reads of a user or a workspace need that record's own view ability; the
| search needs no one ability and answers only for the kinds the admin may
| view. Writing a note or a tag needs `customers.annotate`.
|
*/

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::get('search', CustomerSearchController::class)->middleware($base)->name('search');

foreach ([
    'users' => ['type' => 'user', 'param' => 'user', 'ability' => 'users.view'],
    'workspaces' => ['type' => 'workspace', 'param' => 'workspace', 'ability' => 'workspaces.view'],
] as $segment => $kind) {
    Route::prefix($segment)->as($segment.'.')->group(function () use ($base, $kind) {
        Route::middleware([...$base, 'super-admin.can:'.$kind['ability']])->group(function () use ($kind) {
            Route::get('{'.$kind['param'].'}/overview', [CustomerOverviewController::class, $kind['param']])->whereUuid($kind['param'])->name('overview');
            Route::get('{'.$kind['param'].'}/timeline', [CustomerTimelineController::class, $kind['param']])->whereUuid($kind['param'])->name('timeline');
            Route::get('{id}/annotations', [CustomerAnnotationController::class, 'index'])->whereUuid('id')->defaults('type', $kind['type'])->name('annotations');
        });

        Route::middleware([...$base, 'super-admin.can:customers.annotate'])->group(function () use ($kind) {
            Route::post('{id}/notes', [CustomerAnnotationController::class, 'storeNote'])->whereUuid('id')->defaults('type', $kind['type'])->name('notes.store');
            Route::delete('{id}/notes/{note}', [CustomerAnnotationController::class, 'destroyNote'])->whereUuid(['id', 'note'])->defaults('type', $kind['type'])->name('notes.destroy');
            Route::put('{id}/tags', [CustomerAnnotationController::class, 'syncTags'])->whereUuid('id')->defaults('type', $kind['type'])->name('tags.sync');
        });
    });
}
