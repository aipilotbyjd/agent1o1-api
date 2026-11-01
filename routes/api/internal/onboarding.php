<?php

use App\Http\Controllers\Api\Internal\V1\Onboarding\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::prefix('user')->group(function () {
        Route::get('onboarding', [OnboardingController::class, 'state']);
        Route::post('dismiss-onboarding', [OnboardingController::class, 'dismiss']);
    });

    Route::prefix('onboarding')->group(function () {
        Route::post('invite-team', [OnboardingController::class, 'inviteTeam']);
        Route::post('role', [OnboardingController::class, 'selectRole']);
        Route::post('plan', [OnboardingController::class, 'selectPlan']);
        Route::post('discovery', [OnboardingController::class, 'submitDiscovery']);
        Route::post('continue', [OnboardingController::class, 'continue']);
        Route::post('skip', [OnboardingController::class, 'skip']);
        Route::post('complete', [OnboardingController::class, 'complete']);
    });
});
