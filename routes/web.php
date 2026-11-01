<?php

use App\Http\Controllers\AgentActionSignedDecisionController;
use App\Http\Controllers\BroadcastUnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Signed Agent Action Decisions
|--------------------------------------------------------------------------
|
| Linked from approval emails. The URL signature is the authentication:
| see AgentActionSignedDecisionController.
|
*/

Route::middleware('signed')
    ->prefix('agent-actions/{action}/decision')
    ->name('agent-actions.signed-decision.')
    ->controller(AgentActionSignedDecisionController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'store')->name('store');
    });

/*
|--------------------------------------------------------------------------
| Marketing Email Unsubscribe
|--------------------------------------------------------------------------
|
| Linked from the footer of every marketing campaign. The URL signature is
| the authentication: see BroadcastUnsubscribeController.
|
*/

Route::middleware('signed')
    ->prefix('broadcasts/unsubscribe/{user}')
    ->name('broadcasts.unsubscribe.')
    ->controller(BroadcastUnsubscribeController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'store')->name('store');
    });
