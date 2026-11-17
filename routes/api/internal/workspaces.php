<?php

use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationChannelController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationChannelDeliveryController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationRouteController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\AuditLogController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('workspaces')->group(function () {
    Route::get('/', [WorkspaceController::class, 'index']);
    Route::post('/', [WorkspaceController::class, 'store']);

    // POST, not GET: accepting changes membership, and a GET would be
    // followed by link scanners and mail previews.
    Route::post('invitations/{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->middleware('signed')
        ->name('workspaces.invitations.accept');

    Route::middleware('workspace.context')->prefix('{workspace}')->scopeBindings()->group(function () {
        Route::get('/', [WorkspaceController::class, 'show']);
        Route::patch('/', [WorkspaceController::class, 'update']);
        Route::delete('/', [WorkspaceController::class, 'destroy']);

        Route::get('members', [WorkspaceMemberController::class, 'index']);
        Route::patch('members/{member}', [WorkspaceMemberController::class, 'updateRole']);
        Route::delete('members/{member}', [WorkspaceMemberController::class, 'destroy']);
        Route::post('leave', [WorkspaceMemberController::class, 'leave']);

        Route::get('invitations', [WorkspaceInvitationController::class, 'index']);
        Route::post('invitations', [WorkspaceInvitationController::class, 'store']);
        Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy']);

        Route::get('notification-channels', [NotificationChannelController::class, 'index']);
        Route::post('notification-channels', [NotificationChannelController::class, 'store']);
        Route::patch('notification-channels/{notificationChannel}', [NotificationChannelController::class, 'update']);
        Route::delete('notification-channels/{notificationChannel}', [NotificationChannelController::class, 'destroy']);
        Route::post('notification-channels/{notificationChannel}/test', [NotificationChannelController::class, 'test']);
        Route::post('notification-channels/{notificationChannel}/rotate-secret', [NotificationChannelController::class, 'rotateSecret']);
        Route::get('notification-channels/{notificationChannel}/deliveries', [NotificationChannelDeliveryController::class, 'index']);

        Route::get('notification-routes', [NotificationRouteController::class, 'index']);
        Route::post('notification-routes', [NotificationRouteController::class, 'store']);
        Route::patch('notification-routes/{notificationRoute}', [NotificationRouteController::class, 'update']);
        Route::delete('notification-routes/{notificationRoute}', [NotificationRouteController::class, 'destroy']);

        Route::get('audit-logs', [AuditLogController::class, 'index']);

        Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'upsert']);
        Route::put('notification-preferences/bulk', [NotificationPreferenceController::class, 'bulk']);
    });
});
