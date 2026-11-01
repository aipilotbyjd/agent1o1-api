<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use App\Nodes\Integrations\Stripe\StripeListEventsNode;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Http\Client\RequestException;

/**
 * "New event" in Stripe through a webhook endpoint on the account.
 *
 * One endpoint per set of event types and connected account, shared by every
 * trigger that asks for the same set (no types means every event, `*`). Stripe
 * hands out the endpoint's signing secret once, when it is made, and signs each
 * delivery with it (`Stripe-Signature`, see StripeIngress). A delivery only makes
 * the trigger's own poll look now, so the poll's filters, order and dedupe by
 * event id still decide what runs: an event Stripe delivers twice, or that both
 * push and poll see, starts one run.
 *
 * Stripe switches an endpoint off after days of failed deliveries, so the daily
 * renewal turns it back on, and makes it again if someone deleted it.
 */
class StripeSource extends AbstractWatchedSource
{
    private const string API = 'https://api.stripe.com/v1';

    public function provider(): string
    {
        return 'stripe';
    }

    protected function nodeTypes(): array
    {
        return ['stripe_list_events', 'stripe_dispute_opened', 'stripe_checkout_paid'];
    }

    /**
     * Events of a connected account arrive on a Connect endpoint, which this
     * doesn't make: a trigger that acts on one keeps polling.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && blank($this->nodeConfig($trigger)['stripe_account'] ?? null);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $types = $this->typesOf($trigger);

        sort($types);

        return $types === [] ? 'events:*' : 'events:'.substr(sha1(implode(',', $types)), 0, 24);
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $types = $this->typesOf($trigger);

        $this->register($watch, $types === [] ? ['*'] : $types);
    }

    /**
     * Stripe turns an endpoint off after repeated failures (or someone deleted
     * it): turn it back on, or make it again, keeping the events it was for.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $credential = $this->credentialOf($watch);
        $response = $this->http($credential)->get(self::API.'/webhook_endpoints/'.rawurlencode((string) $watch->external_id));

        if ($response->status() === 404) {
            $this->register($watch, (array) ($watch->meta['events'] ?? ['*']));
        } elseif ($response->successful() && $response->json('status') === 'disabled') {
            $this->ok($this->http($credential)->asForm()->post(self::API.'/webhook_endpoints/'.rawurlencode((string) $watch->external_id), ['disabled' => 'false']));
        } else {
            $this->ok($response);
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $response = $this->http($this->credentialOf($watch))->asForm()->delete(self::API.'/webhook_endpoints/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Deliveries are filed under the endpoint that sent them.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * Make the endpoint and keep the secret Stripe shows only now.
     *
     * @param  list<string>  $events
     *
     * @throws RequestException when Stripe refuses it (a URL that isn't public https, a key that can't write endpoints)
     */
    private function register(ProviderWatch $watch, array $events): void
    {
        $response = $this->ok($this->http($this->credentialOf($watch))->asForm()->post(self::API.'/webhook_endpoints', [
            'url' => $this->callbackUrl(),
            'enabled_events' => $events,
            'description' => 'Flows triggers',
        ]));

        $secret = $response->json('secret');

        if (! is_string($secret) || $secret === '') {
            throw new RequestException($response);
        }

        $watch->external_id = (string) $response->json('id');
        $watch->secret = $secret;
        $watch->meta = [...(array) $watch->meta, 'events' => $events];
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * The event types a trigger names, the same way the node reads them.
     *
     * @return list<string>
     */
    private function typesOf(Trigger $trigger): array
    {
        $node = app(NodeRegistry::class)->resolve((string) $trigger->config['node_type']);

        return ($node instanceof StripeListEventsNode ? $node : new StripeListEventsNode)->eventTypes($this->nodeConfig($trigger));
    }
}
