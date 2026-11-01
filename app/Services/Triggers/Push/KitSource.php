<?php

namespace App\Services\Triggers\Push;

use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Kit (ConvertKit) triggers through its v4 webhooks.
 *
 * Kit has one webhook per event (and per tag or form for the events that name
 * one), so triggers on the same event share a watch. Kit does not sign them, so
 * the URL carries a random token (kept hashed); a delivery only makes the
 * trigger's own poll look now.
 *
 * "New subscriber" listens for a subscriber becoming active, so with a
 * confirmation email the instant part comes when they confirm; the poll sees
 * the signup itself within its interval.
 */
class KitSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.kit.com/v4';

    /**
     * Each reader node's webhook event, and the setting that names what it is about.
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    private const array EVENTS = [
        'kit_list_subscribers' => ['subscriber.subscriber_activate', null],
        'kit_list_unsubscribes' => ['subscriber.subscriber_unsubscribe', null],
        'kit_list_bounced_subscribers' => ['subscriber.subscriber_bounce', null],
        'kit_list_tagged_subscribers' => ['subscriber.tag_add', 'tag_id'],
        'kit_list_form_subscribers' => ['subscriber.form_subscribe', 'form_id'],
        'kit_list_purchases' => ['purchase.purchase_create', null],
    ];

    public function provider(): string
    {
        return 'kit';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    /**
     * A trigger that names its tag or form, where the event needs one.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        [$event, $param] = self::EVENTS[$trigger->config['node_type'] ?? ''] ?? [null, null];

        if ($event === null) {
            return null;
        }

        if ($param === null) {
            return $event;
        }

        $id = $this->nodeConfig($trigger)[$param] ?? null;

        return is_numeric($id) && (int) $id > 0 ? "{$event}:{$param}:".(int) $id : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Kit retires a webhook that keeps failing, or someone removed it: look for it, and make it again if it is gone.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $page = $this->ok($this->kitHttp($credential)->get(self::API.'/webhooks', ['per_page' => 1000]))->json('webhooks', []);
        $found = collect((array) $page)->first(fn (mixed $hook): bool => is_array($hook) && (string) ($hook['id'] ?? '') === (string) $watch->external_id);

        if ($found === null || ($found['active'] ?? true) === false) {
            $this->register($watch);

            return;
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->kitHttp($this->credentialOf($watch))->delete(self::API.'/webhooks/'.$watch->external_id);

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Make the webhook for the watch's event, with a fresh token URL.
     */
    private function register(ProviderWatch $watch): void
    {
        $parts = explode(':', $watch->resource_key);
        $event = array_filter(['name' => $parts[0], $parts[1] ?? '' => isset($parts[2]) ? (int) $parts[2] : null], fn (mixed $value, mixed $key): bool => $value !== null && $key !== '', ARRAY_FILTER_USE_BOTH);

        $this->registerUrl($watch, function (string $url) use ($watch, $event): void {
            $response = $this->ok($this->kitHttp($this->credentialOf($watch))->post(self::API.'/webhooks', ['target_url' => $url, 'event' => $event]));

            $watch->external_id = (string) $response->json('webhook.id');
        });

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * Kit takes the key in a header.
     */
    private function kitHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['X-Kit-Api-Key' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(20);
    }
}
