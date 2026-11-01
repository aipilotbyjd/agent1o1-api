<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Resend triggers through its webhooks.
 *
 * One webhook per set of events, shared by the triggers that watch the same
 * one. Resend signs its deliveries with a secret it makes (Svix), but a delivery
 * here only makes the trigger's own poll look now, so the URL's random token
 * (kept hashed) is the check and the signing secret is not needed.
 *
 * The "email event" node picks the event it is about: "any" listens to all of them.
 */
class ResendSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.resend.com';

    private const array EMAIL_EVENTS = ['sent', 'delivered', 'delivery_delayed', 'bounced', 'complained', 'opened', 'clicked', 'failed'];

    public function provider(): string
    {
        return 'resend';
    }

    protected function nodeTypes(): array
    {
        return ['resend_list_emails', 'resend_list_contacts'];
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        if (($trigger->config['node_type'] ?? '') === 'resend_list_contacts') {
            return 'contact.created';
        }

        $event = (string) ($this->nodeConfig($trigger)['event'] ?? 'any');

        return $event === 'any' || in_array($event, self::EMAIL_EVENTS, true) ? 'email.'.$event : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Resend disables a webhook that keeps failing, or someone removed it: make it again if so.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $response = $this->resendHttp($this->credentialOf($watch))->get(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() === 404 || ($response->successful() && $response->json('status') === 'disabled')) {
            $this->register($watch);

            return;
        }

        $this->ok($response);

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->resendHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's events, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $key = $watch->resource_key;
        $events = $key === 'email.any' ? array_map(fn (string $event): string => 'email.'.$event, self::EMAIL_EVENTS) : [$key];

        $this->registerUrl($watch, function (string $url) use ($watch, $events): void {
            $response = $this->ok($this->resendHttp($this->credentialOf($watch))->post(self::API.'/webhooks', ['endpoint' => $url, 'events' => $events]));
            $watch->external_id = (string) $response->json('id');
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function resendHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withToken($this->tokens->accessToken($credential))->acceptJson()->timeout(20);
    }
}
