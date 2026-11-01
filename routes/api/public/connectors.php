<?php

use App\Http\Controllers\Api\Public\V1\Connectors\ConnectorController;
use App\Http\Controllers\Api\Public\V1\Connectors\ConnectorCredentialController;
use Illuminate\Support\Facades\Route;

/*
 * Credential provisioning for integrators: see
 * ConnectorCredentialController. OAuth connectors are refused: their tokens
 * can only come from a browser redirect.
 */
Route::middleware('api-key:connectors:manage')->group(function () {
    Route::get('connectors', [ConnectorController::class, 'index']);

    Route::get('connector-credentials', [ConnectorCredentialController::class, 'index']);
    Route::post('connector-credentials', [ConnectorCredentialController::class, 'store']);
    Route::get('connector-credentials/{connectorCredential}', [ConnectorCredentialController::class, 'show']);
    Route::delete('connector-credentials/{connectorCredential}', [ConnectorCredentialController::class, 'destroy']);
});
