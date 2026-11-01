<?php

use App\Http\Controllers\Api\Internal\V1\Connectors\OAuthConnectorController;
use App\Http\Controllers\Webhooks\AssistantEmailWebhookController;
use App\Http\Controllers\Webhooks\AssistantSlackEventsController;
use App\Http\Controllers\Webhooks\AssistantSlackOAuthController;
use App\Http\Controllers\Webhooks\AssistantSmsWebhookController;
use App\Http\Controllers\Webhooks\AssistantWebhookController;
use App\Http\Controllers\Webhooks\ProviderIngressController;
use App\Http\Controllers\Webhooks\SlackAgentActionController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\WaitCallbackController;
use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inbound Webhooks
|--------------------------------------------------------------------------
|
| Every route here is public: each one authenticates itself (a URL token,
| a provider signature, or an OAuth `state`) rather than through a session
| or API key. They live outside routes/api/{internal,public}/ so they don't
| inherit those middleware groups. See docs/TRIGGERS_PLAN.md.
|
*/

Route::prefix('hooks')->name('hooks.')->group(function (): void {
    // Workflow trigger: the token in the URL is the credential.
    Route::post('{token}', WebhookController::class)
        ->middleware('throttle:trigger-hooks')
        ->name('trigger');

    // Events a provider pushes for every connected account (Slack's Events
    // API): checked by the provider's own signature, not a URL token.
    Route::post('apps/{provider}', ProviderIngressController::class)
        ->middleware('throttle:1200,1')
        ->name('apps');

    // A provider confirming a subscription with a GET (YouTube's WebSub hub):
    // answered only for a channel we are watching, by the provider's ingress.
    Route::get('apps/{provider}', ProviderIngressController::class)
        ->middleware('throttle:1200,1')
        ->name('apps.verify');

    // A provider confirming it has the right URL with a GET (Meta's
    // hub.challenge). Answered only for a webhook with a secret set.
    Route::get('{token}', WebhookController::class)
        ->middleware('throttle:trigger-hooks')
        ->name('trigger.verify');

    // A Wait node's one-time callback token. Reuses the 'trigger-hooks'
    // limiter, which is keyed generically by the {token} route param.
    Route::post('wait/{token}', WaitCallbackController::class)
        ->middleware('throttle:trigger-hooks')
        ->name('wait-callback');

    Route::prefix('assistant')->name('assistant.')->group(function (): void {
        // The assistant's own channels. Declared before assistant/{token}
        // so these fixed paths are never read as a trigger token.
        Route::post('email', AssistantEmailWebhookController::class)
            ->middleware('throttle:120,1')
            ->name('email');

        Route::post('sms', AssistantSmsWebhookController::class)
            ->middleware('throttle:300,1')
            ->name('sms');

        Route::post('slack/events', AssistantSlackEventsController::class)
            ->name('slack.events');

        Route::get('slack/oauth', AssistantSlackOAuthController::class)
            ->name('slack.oauth');
    });

    // A personal assistant's webhook trigger: the token in the URL is the
    // credential. Answers at once; the assistant runs on the queue.
    Route::post('assistant/{token}', AssistantWebhookController::class)
        ->middleware('throttle:assistant-hooks')
        ->name('assistant');
});

/*
|--------------------------------------------------------------------------
| OAuth Callbacks
|--------------------------------------------------------------------------
|
| The provider redirects the user's browser here after the consent screen,
| so no session or API key is available. Tenant safety comes from the
| unguessable `state` query param OAuthConnectorController looks up.
|
*/

Route::get('oauth/connectors/callback', [OAuthConnectorController::class, 'callback'])
    ->name('oauth.connectors.callback');

/*
|--------------------------------------------------------------------------
| Slack Interactivity
|--------------------------------------------------------------------------
|
| Approve/reject buttons on agent approval messages. Authenticated by
| Slack's request signature and the button's encrypted value: see
| SlackAgentActionController.
|
*/

Route::post('slack/agent-actions', SlackAgentActionController::class)
    ->middleware('throttle:trigger-hooks')
    ->name('slack.agent-actions');

/*
|--------------------------------------------------------------------------
| Stripe
|--------------------------------------------------------------------------
|
| Cashier's auto-registration is disabled (AppServiceProvider::configureCashier)
| so this resolves to our own controller: idempotency guard plus plan and
| usage-period sync, instead of Cashier's default one.
|
*/

Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
