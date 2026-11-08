<?php

use App\Http\Controllers\Api\Internal\V1\Ai\AiProviderConnectController;
use App\Http\Controllers\Api\Internal\V1\Ai\AiProviderCredentialController;
use App\Http\Controllers\Api\Internal\V1\Ai\WorkspaceAiKeyPolicyController;
use Illuminate\Support\Facades\Route;

// Finishes a one-click connection (`AiProviderConnectFlow`): the workspace
// comes from the sign-in's `state`, and only the member who started it can.
Route::post('ai-providers/connect/complete', [AiProviderConnectController::class, 'complete'])
    ->middleware(['auth:api', 'throttle:ai-credential-checks'])
    ->name('ai-providers.connect.complete');

Route::middleware(['auth:api', 'workspace.context'])
    ->prefix('workspaces/{workspace}')
    ->scopeBindings()
    ->group(function () {
        Route::get('ai-providers', [AiProviderCredentialController::class, 'providers'])->name('ai-providers.index');
        Route::post('ai-providers/{provider}/connect', [AiProviderConnectController::class, 'start'])->middleware('throttle:ai-credential-checks')->name('ai-providers.connect');
        Route::get('ai-key-policy', [WorkspaceAiKeyPolicyController::class, 'show'])->name('ai-key-policy.show');
        Route::put('ai-key-policy', [WorkspaceAiKeyPolicyController::class, 'update'])->name('ai-key-policy.update');
        Route::post('ai-key-policy/preview', [WorkspaceAiKeyPolicyController::class, 'preview'])->name('ai-key-policy.preview');

        Route::prefix('ai-provider-credentials')->as('ai-provider-credentials.')->group(function () {
            Route::get('/', [AiProviderCredentialController::class, 'index'])->name('index');
            Route::post('/', [AiProviderCredentialController::class, 'store'])->middleware('throttle:ai-credential-checks')->name('store');
            Route::patch('{aiProviderCredential}', [AiProviderCredentialController::class, 'update'])->middleware('throttle:ai-credential-checks')->name('update');
            Route::delete('{aiProviderCredential}', [AiProviderCredentialController::class, 'destroy'])->name('destroy');
            Route::post('{aiProviderCredential}/restore', [AiProviderCredentialController::class, 'restore'])->withTrashed()->name('restore');
            Route::post('{aiProviderCredential}/default', [AiProviderCredentialController::class, 'setDefault'])->name('set-default');
            Route::post('{aiProviderCredential}/validate', [AiProviderCredentialController::class, 'validateKey'])->middleware('throttle:ai-credential-checks')->name('validate');
        });
    });
