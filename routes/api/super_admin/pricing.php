<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingCouponController;
use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingCreditPolicyController;
use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingIntervalController;
use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingOverviewController;
use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingPackController;
use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingPlanController;
use App\Http\Controllers\Api\SuperAdmin\V1\Pricing\PricingPlanPriceController;
use Illuminate\Support\Facades\Route;

$base = [
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit',
];

Route::prefix('pricing')->as('pricing.')->group(function () use ($base) {
    Route::middleware([...$base, 'super-admin.can:pricing.view'])->group(function () {
        Route::get('overview', [PricingOverviewController::class, 'overview'])->name('overview');
        Route::get('changes', [PricingOverviewController::class, 'changes'])->name('changes');

        Route::get('plans', [PricingPlanController::class, 'index'])->name('plans.index');
        Route::get('plans/{plan}', [PricingPlanController::class, 'show'])->whereUuid('plan')->name('plans.show');
        Route::get('plans/{plan}/subscribers', [PricingPlanController::class, 'subscribers'])->whereUuid('plan')->name('plans.subscribers');
        Route::get('plans/{plan}/changes', [PricingPlanController::class, 'changes'])->whereUuid('plan')->name('plans.changes');
        Route::post('plans/{plan}/preview', [PricingPlanController::class, 'preview'])->whereUuid('plan')->name('plans.preview');

        Route::get('packs', [PricingPackController::class, 'index'])->name('packs.index');
        Route::get('intervals', [PricingIntervalController::class, 'index'])->name('intervals.index');
        Route::get('credit-policies', [PricingCreditPolicyController::class, 'index'])->name('credit-policies.index');

        Route::get('coupons', [PricingCouponController::class, 'index'])->name('coupons.index');
        Route::get('coupons/{coupon}', [PricingCouponController::class, 'show'])->whereUuid('coupon')->name('coupons.show');
    });

    // Edits that change nothing a customer is charged.
    Route::middleware([...$base, 'super-admin.can:pricing.manage'])->group(function () {
        Route::post('plans', [PricingPlanController::class, 'store'])->name('plans.store');
        Route::post('plans/reorder', [PricingPlanController::class, 'reorder'])->name('plans.reorder');
        Route::patch('plans/{plan}', [PricingPlanController::class, 'update'])->whereUuid('plan')->name('plans.update');
        Route::post('plans/{plan}/duplicate', [PricingPlanController::class, 'duplicate'])->whereUuid('plan')->name('plans.duplicate');
    });

    // Sensitive: a reason and a fresh authenticator code (the form requests),
    // a tight rate limit, and safe to retry with an Idempotency-Key.
    $moving = ['throttle:super-admin-sensitive', 'super-admin.idempotent'];

    Route::middleware([...$base, 'super-admin.can:pricing.manage', ...$moving])->group(function () {
        Route::post('plans/{plan}/activate', [PricingPlanController::class, 'activate'])->whereUuid('plan')->name('plans.activate');
        Route::post('plans/{plan}/deactivate', [PricingPlanController::class, 'deactivate'])->whereUuid('plan')->name('plans.deactivate');
        Route::post('plans/{plan}/archive', [PricingPlanController::class, 'archive'])->whereUuid('plan')->name('plans.archive');
        Route::post('plans/{plan}/restore', [PricingPlanController::class, 'restore'])->whereUuid('plan')->name('plans.restore');
        Route::delete('plans/{plan}', [PricingPlanController::class, 'destroy'])->whereUuid('plan')->name('plans.destroy');
    });

    // Anything that changes what customers pay, or creates objects in Stripe.
    Route::middleware([...$base, 'super-admin.can:pricing.prices', ...$moving])->group(function () {
        Route::put('plans/{plan}/prices', [PricingPlanPriceController::class, 'update'])->whereUuid('plan')->name('plans.prices.update');
        Route::post('plans/{plan}/stripe/sync', [PricingPlanPriceController::class, 'sync'])->whereUuid('plan')->name('plans.stripe.sync');

        Route::post('packs', [PricingPackController::class, 'store'])->name('packs.store');
        Route::patch('packs/{pack}', [PricingPackController::class, 'update'])->whereUuid('pack')->name('packs.update');
        Route::delete('packs/{pack}', [PricingPackController::class, 'destroy'])->whereUuid('pack')->name('packs.destroy');

        Route::put('intervals', [PricingIntervalController::class, 'update'])->name('intervals.update');
        Route::put('credit-policies', [PricingCreditPolicyController::class, 'update'])->name('credit-policies.update');

        Route::post('coupons', [PricingCouponController::class, 'store'])->name('coupons.store');
        Route::post('coupons/{coupon}/activate', [PricingCouponController::class, 'activate'])->whereUuid('coupon')->name('coupons.activate');
        Route::post('coupons/{coupon}/deactivate', [PricingCouponController::class, 'deactivate'])->whereUuid('coupon')->name('coupons.deactivate');
    });
});
