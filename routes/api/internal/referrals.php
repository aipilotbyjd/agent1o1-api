<?php

use App\Http\Controllers\Api\Internal\V1\Referrals\ReferralClaimController;
use App\Http\Controllers\Api\Internal\V1\Referrals\ReferralController;
use App\Http\Controllers\Api\Internal\V1\Referrals\ReferralVisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('referrals')->as('referrals.')->group(function () {
    // Public: someone landed on a `?ref=` link and has no account yet.
    Route::post('visit', [ReferralVisitController::class, 'store'])
        ->middleware('throttle:referral-visits')
        ->name('visit');

    Route::middleware('auth:api')->group(function () {
        Route::post('claim', [ReferralClaimController::class, 'store'])
            ->middleware('throttle:referral-claims')
            ->name('claim');

        Route::get('me', [ReferralController::class, 'me'])->name('me');
        Route::patch('me', [ReferralController::class, 'update'])->name('me.update');
        Route::get('program', [ReferralController::class, 'program'])->name('program');
        Route::get('stats', [ReferralController::class, 'stats'])->name('stats');
        Route::get('rewards', [ReferralController::class, 'rewards'])->name('rewards');
        Route::get('/', [ReferralController::class, 'index'])->name('index');
    });
});
