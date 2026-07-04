<?php

namespace App\Services;

use App\Contracts\WebhookRegistrar;
use App\Engine\Webhook\WebhookRegistrarRegistry;
use App\Models\Trigger;
use Illuminate\Support\Facades\Log;

/**
 * Registers and unregisters app-specific webhook triggers with their providers
 * (GitHub, Stripe, Slack, …) and verifies incoming signatures.
 */
class TriggerRegistrationService
{
    /**
     * Register an app-specific webhook trigger with its provider. Best-effort:
     * failures are recorded on the trigger's webhook_status and rethrown.
     *
     * @throws \Throwable
     */
    public function registerWebhookTrigger(Trigger $trigger): void
    {
        $provider = $trigger->webhook_provider;
        $registrar = $provider ? WebhookRegistrarRegistry::resolveRegisterable($provider) : null;

        if (! $registrar) {
            // Provider needs no API registration (e.g. Discord) or is unknown.
            $trigger->update(['webhook_status' => 'active', 'webhook_status_message' => null]);

            return;
        }

        try {
            $credential = $trigger->credential;

            if (! $credential) {
                throw new \RuntimeException('No credential provided for webhook registration');
            }

            $credentials = $credential->getDecryptedData();
            $providerConfig = $trigger->getFieldValues();
            $events = $trigger->triggerType?->webhook_events ?? [];
            $callbackUrl = $this->callbackUrl($trigger);

            $result = $registrar->register($callbackUrl, $events, $credentials, $providerConfig);

            if (! isset($result['external_id'])) {
                throw new \RuntimeException('Registrar returned invalid response (missing external_id)');
            }

            $trigger->update([
                'webhook_external_id' => $result['external_id'],
                'webhook_secret' => $result['secret'] ?? $trigger->webhook_secret,
                'webhook_registered_url' => $callbackUrl,
                'webhook_status' => 'active',
                'webhook_status_message' => null,
            ]);

            Log::info('Webhook registered', ['trigger_id' => $trigger->id, 'provider' => $provider]);
        } catch (\Throwable $e) {
            Log::error('Webhook registration failed', [
                'trigger_id' => $trigger->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $trigger->update(['webhook_status' => 'failed', 'webhook_status_message' => $e->getMessage()]);

            throw $e;
        }
    }

    public function unregisterWebhookTrigger(Trigger $trigger): void
    {
        $provider = $trigger->webhook_provider;

        if (! $provider || ! $trigger->webhook_external_id) {
            return;
        }

        $registrar = WebhookRegistrarRegistry::resolveRegisterable($provider);

        if (! $registrar) {
            return;
        }

        try {
            $credential = $trigger->credential;

            if (! $credential) {
                return;
            }

            $registrar->unregister(
                $trigger->webhook_external_id,
                $credential->getDecryptedData(),
                $trigger->getFieldValues(),
            );

            Log::info('Webhook unregistered', ['trigger_id' => $trigger->id, 'provider' => $provider]);
        } catch (\Throwable $e) {
            Log::error('Webhook unregistration failed', [
                'trigger_id' => $trigger->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function verifyWebhookSignature(Trigger $trigger, string $payload, string $signature): bool
    {
        $provider = $trigger->webhook_provider;
        $registrar = $provider ? WebhookRegistrarRegistry::resolve($provider) : null;

        if (! $registrar) {
            return false;
        }

        try {
            return $registrar->verifySignature($payload, $signature, (string) $trigger->webhook_secret);
        } catch (\Throwable $e) {
            Log::error('Webhook signature verification errored', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function callbackUrl(Trigger $trigger): string
    {
        return rtrim((string) config('app.url'), '/')."/api/v1/webhooks/{$trigger->webhook_uuid}";
    }

    /**
     * Resolve a registrar for a provider, or null when unsupported.
     */
    public function registrarFor(string $provider): ?WebhookRegistrar
    {
        return WebhookRegistrarRegistry::resolve($provider);
    }
}
