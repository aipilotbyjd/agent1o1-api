<?php

use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationChannelController;
use App\Http\Controllers\Api\Internal\V1\Notifications\NotificationPreferenceController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\AuditLogController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Api\Internal\V1\Workspaces\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('workspaces')->group(function () {
    Route::get('/', [WorkspaceController::class, 'index']);
    Route::post('/', [WorkspaceController::class, 'store']);

    Route::get('invitations/{invitation}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->middleware('signed')
        ->name('workspaces.invitations.accept');

    Route::middleware('workspace.context')->prefix('{workspace}')->group(function () {
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

        Route::get('audit-logs', [AuditLogController::class, 'index']);

        Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('notification-preferences', [NotificationPreferenceController::class, 'upsert']);
    });
});
