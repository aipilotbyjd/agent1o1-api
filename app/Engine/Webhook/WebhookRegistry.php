<?php

namespace App\Engine\Webhook;

use App\Models\Trigger;
use Illuminate\Support\Str;

class WebhookRegistry
{
    /**
     * Find an active webhook trigger by its public UUID.
     */
    public function findByUuid(string $webhookUuid): ?Trigger
    {
        return Trigger::where('webhook_uuid', $webhookUuid)
            ->where('type', 'webhook')
            ->where('is_active', true)
            ->where('is_paused', false)
            ->first();
    }

    /**
     * Provision a webhook UUID + secret for a trigger.
     */
    public function provision(Trigger $trigger): Trigger
    {
        if (! $trigger->webhook_uuid) {
            $trigger->update([
                'webhook_uuid' => Str::uuid()->toString(),
                'webhook_secret' => Str::random(40),
                'webhook_status' => 'active',
            ]);
        }

        return $trigger;
    }

    /**
     * Verify an incoming webhook's signature if the trigger has a secret.
     */
    public function verifySignature(Trigger $trigger, string $payload, ?string $signature): bool
    {
        if (! $trigger->webhook_secret) {
            return true;
        }

        if ($signature === null) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $trigger->webhook_secret);

        return hash_equals($expected, $signature);
    }

    public function publicUrl(Trigger $trigger): string
    {
        return url("/api/v1/webhooks/{$trigger->webhook_uuid}");
    }
}
