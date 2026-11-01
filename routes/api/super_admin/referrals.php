<?php

use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\AdminReferralCodeController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\AdminReferralController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\AdminReferralRewardController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\ReferralBlockedDomainController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\ReferralProgramController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\ReferralRewardRuleController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\ReferralSimulationController;
use App\Http\Controllers\Api\SuperAdmin\V1\Referrals\ReferralStatsController;
use Illuminate\Support\Facades\Route;

/*
| The referral program: programs, reward rules, codes, referrals, the reward
| ledger and the blocked-domain list. Needs `referrals.manage`.
*/
Route::middleware([
    'throttle:super-admin', 'auth:super-admin', 'super-admin.ip:account', 'super-admin.active',
    'super-admin.two-factor', 'super-admin.audit', 'super-admin.can:referrals.manage',
])->prefix('referrals')->as('referrals.')->group(function () {
    Route::get('stats', [ReferralStatsController::class, 'show'])->name('stats');

    Route::get('programs', [ReferralProgramController::class, 'index'])->name('programs.index');
    Route::post('programs', [ReferralProgramController::class, 'store'])->name('programs.store');
    Route::get('programs/{program}', [ReferralProgramController::class, 'show'])->name('programs.show');
    Route::patch('programs/{program}', [ReferralProgramController::class, 'update'])->name('programs.update');
    Route::delete('programs/{program}', [ReferralProgramController::class, 'destroy'])->name('programs.destroy');
    Route::post('programs/{program}/make-default', [ReferralProgramController::class, 'makeDefault'])->name('programs.make-default');
    Route::post('programs/{program}/duplicate', [ReferralProgramController::class, 'duplicate'])->name('programs.duplicate');
    Route::post('programs/{program}/simulate', [ReferralSimulationController::class, 'store'])->name('programs.simulate');

    Route::get('programs/{program}/rules', [ReferralRewardRuleController::class, 'index'])->name('rules.index');
    Route::post('programs/{program}/rules', [ReferralRewardRuleController::class, 'store'])->name('rules.store');
    Route::patch('programs/{program}/rules/reorder', [ReferralRewardRuleController::class, 'reorder'])->name('rules.reorder');
    Route::patch('rules/{rule}', [ReferralRewardRuleController::class, 'update'])->name('rules.update');
    Route::delete('rules/{rule}', [ReferralRewardRuleController::class, 'destroy'])->name('rules.destroy');
    Route::post('rules/{rule}/toggle', [ReferralRewardRuleController::class, 'toggle'])->name('rules.toggle');

    Route::get('codes', [AdminReferralCodeController::class, 'index'])->name('codes.index');
    Route::patch('codes/{code}', [AdminReferralCodeController::class, 'update'])->name('codes.update');

    Route::get('referrals', [AdminReferralController::class, 'index'])->name('referrals.index');
    Route::get('referrals/{referral}', [AdminReferralController::class, 'show'])->name('referrals.show');
    Route::post('referrals/{referral}/reject', [AdminReferralController::class, 'reject'])->name('referrals.reject');
    Route::post('referrals/{referral}/restore', [AdminReferralController::class, 'restore'])->name('referrals.restore');

    Route::get('rewards', [AdminReferralRewardController::class, 'index'])->name('rewards.index');
    Route::post('rewards/manual', [AdminReferralRewardController::class, 'store'])->name('rewards.store');
    Route::post('rewards/{reward}/approve', [AdminReferralRewardController::class, 'approve'])->name('rewards.approve');
    Route::post('rewards/{reward}/grant-now', [AdminReferralRewardController::class, 'grantNow'])->name('rewards.grant-now');
    Route::post('rewards/{reward}/revoke', [AdminReferralRewardController::class, 'revoke'])->name('rewards.revoke');

    Route::get('blocked-domains', [ReferralBlockedDomainController::class, 'index'])->name('blocked-domains.index');
    Route::post('blocked-domains', [ReferralBlockedDomainController::class, 'store'])->name('blocked-domains.store');
    Route::delete('blocked-domains/{domain}', [ReferralBlockedDomainController::class, 'destroy'])->name('blocked-domains.destroy');
});
