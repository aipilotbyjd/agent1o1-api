<?php

use App\Http\Controllers\Api\SuperAdmin\V1\AuditLog\SuperAdminAuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit', 'super-admin.can:audit.view',
])->prefix('audit-log')->as('audit-log.')->group(function () {
    Route::get('/', [SuperAdminAuditLogController::class, 'index'])->name('index');
    Route::get('integrity', [SuperAdminAuditLogController::class, 'integrity'])->name('integrity');
    Route::get('{log}', [SuperAdminAuditLogController::class, 'show'])->whereUuid('log')->name('show');
});
