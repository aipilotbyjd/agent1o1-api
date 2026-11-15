<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Operations\OperationsController;
use App\Http\Controllers\Api\SuperAdmin\V1\Operations\OperationsTaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operations checklist
|--------------------------------------------------------------------------
|
| The recurring setup, daily, weekly and monthly tasks that keep the platform
| healthy. Everyone who can sign in can read the checklist; operators tick
| tasks off and snooze them; only owners change the list itself.
|
*/

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('operations')->as('operations.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:operations.view'])->group(function () {
        Route::get('/', [OperationsController::class, 'index'])->name('index');
        Route::get('summary', [OperationsController::class, 'summary'])->name('summary');
        Route::get('history', [OperationsController::class, 'history'])->name('history');
    });

    Route::middleware([...$base, 'super-admin.can:operations.manage'])->group(function () {
        Route::post('tasks/{task}/complete', [OperationsTaskController::class, 'complete'])->whereUuid('task')->name('tasks.complete');
        Route::delete('tasks/{task}/complete', [OperationsTaskController::class, 'reopen'])->whereUuid('task')->name('tasks.reopen');
        Route::post('tasks/{task}/snooze', [OperationsTaskController::class, 'snooze'])->whereUuid('task')->name('tasks.snooze');
    });

    Route::middleware([...$base, 'super-admin.can:operations.configure'])->group(function () {
        Route::get('tasks', [OperationsTaskController::class, 'index'])->name('tasks.index');
        Route::post('tasks', [OperationsTaskController::class, 'store'])->name('tasks.store');
        Route::patch('tasks/{task}', [OperationsTaskController::class, 'update'])->whereUuid('task')->name('tasks.update');
        Route::delete('tasks/{task}', [OperationsTaskController::class, 'destroy'])->whereUuid('task')->name('tasks.destroy');
    });
});
