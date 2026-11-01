<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Billing\SuperAdminBillingController;
use App\Http\Controllers\Api\SuperAdmin\V1\Billing\SuperAdminWebhookController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('billing')->as('billing.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:billing.view'])->group(function () {
        Route::get('plans', [SuperAdminBillingController::class, 'plans'])->name('plans');
        Route::get('webhooks/failures', [SuperAdminWebhookController::class, 'failures'])->name('webhooks.failures');
        Route::get('workspaces/{workspace}', [SuperAdminBillingController::class, 'show'])->whereUuid('workspace')->name('show');
        Route::get('workspaces/{workspace}/credit-transactions', [SuperAdminBillingController::class, 'creditTransactions'])->whereUuid('workspace')->name('credit-transactions');
        Route::get('workspaces/{workspace}/plan-grants', [SuperAdminBillingController::class, 'planGrants'])->whereUuid('workspace')->name('plan-grants.index');
    });

    // Money-moving: a reason and a fresh authenticator code (the form
    // requests), a tight rate limit, and safe to retry with an Idempotency-Key.
    $moving = ['throttle:super-admin-sensitive', 'super-admin.idempotent'];

    Route::middleware([...$base, 'super-admin.can:billing.grant-credits', ...$moving])->group(function () {
        Route::post('workspaces/{workspace}/credits/grant', [SuperAdminBillingController::class, 'grantCredits'])->whereUuid('workspace')->name('credits.grant');
        Route::post('workspaces/{workspace}/credits/revoke', [SuperAdminBillingController::class, 'revokeCredits'])->whereUuid('workspace')->name('credits.revoke');
    });

    Route::middleware([...$base, 'super-admin.can:billing.extend-plan', ...$moving])->group(function () {
        Route::post('workspaces/{workspace}/plan-grants', [SuperAdminBillingController::class, 'storePlanGrant'])->whereUuid('workspace')->name('plan-grants.store');
        Route::delete('plan-grants/{grant}', [SuperAdminBillingController::class, 'destroyPlanGrant'])->whereUuid('grant')->name('plan-grants.destroy');
    });

    Route::post('webhooks/{eventId}/replay', [SuperAdminWebhookController::class, 'replay'])
        ->middleware([...$base, 'super-admin.can:billing.replay-webhooks', 'throttle:super-admin-sensitive'])
        ->where('eventId', 'evt_[A-Za-z0-9_]+')
        ->name('webhooks.replay');

    Route::middleware([...$base, 'super-admin.can:billing.refund', ...$moving])->group(function () {
        Route::post('workspaces/{workspace}/refunds', [SuperAdminBillingController::class, 'refund'])->whereUuid('workspace')->name('refunds.store');
    });
});
