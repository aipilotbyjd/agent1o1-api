<?php

use App\Http\Controllers\Api\Internal\V1\Connectors\ConnectorController;
use App\Http\Controllers\Api\Internal\V1\Connectors\ConnectorCredentialController;
use App\Http\Controllers\Api\Internal\V1\Connectors\OAuthConnectorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    // Finishes an OAuth connection (`OAuthConnectorFlowService`): the workspace
    // comes from the sign-in's `state`, and only the member who started it can.
    Route::post('connector-credentials/oauth/complete', [OAuthConnectorController::class, 'complete'])
        ->middleware('throttle:60,1')
        ->name('connector-credentials.oauth.complete');

    Route::get('connectors', [ConnectorController::class, 'index'])->name('connectors.index');
    Route::get('connectors/{connector}', [ConnectorController::class, 'show'])->name('connectors.show');
});

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}/connector-credentials')
    ->as('connector-credentials.')
    ->group(function () {
        Route::post('oauth/initiate', [OAuthConnectorController::class, 'initiate'])->name('oauth.initiate');

        Route::get('/', [ConnectorCredentialController::class, 'index'])->name('index');
        Route::post('/', [ConnectorCredentialController::class, 'store'])->name('store');
        Route::get('{connectorCredential}', [ConnectorCredentialController::class, 'show'])->name('show');
        Route::patch('{connectorCredential}', [ConnectorCredentialController::class, 'update'])->name('update');
        Route::delete('{connectorCredential}', [ConnectorCredentialController::class, 'destroy'])->name('destroy');
        Route::post('{connectorCredential}/default', [ConnectorCredentialController::class, 'setDefault'])->name('set-default');
        Route::post('{connectorCredential}/test', [ConnectorCredentialController::class, 'test'])->middleware('throttle:connector-tests')->name('test');
        Route::get('{connectorCredential}/usage', [ConnectorCredentialController::class, 'usage'])->name('usage');
    });
