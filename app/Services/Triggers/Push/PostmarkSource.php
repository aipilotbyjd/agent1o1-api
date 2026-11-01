<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\ConnectorException;
use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Postmark triggers through its webhooks.
 *
 * Delivery, bounce, spam complaint, open and click events each get a webhook on
 * the message stream, shared by the triggers that watch the same event. Received
 * email is different: a server has exactly one inbound webhook URL (a server
 * setting), so it is used only when it is empty, and cleared again when the
 * last trigger goes. Postmark doesn't sign these, so the URL carries a random
 * token (kept hashed); a delivery only makes the trigger's own poll look now.
 */
class PostmarkSource extends AbstractTokenWatchedSource
{
    private const string API = 'https://api.postmarkapp.com';

    /**
     * Each reader node's webhook trigger name in Postmark.
     *
     * @var array<string, string>
     */
    private const array EVENTS = [
        'postmark_list_outbound_messages' => 'Delivery',
        'postmark_list_bounces' => 'Bounce',
        'postmark_list_spam_complaints' => 'SpamComplaint',
        'postmark_list_opens' => 'Open',
        'postmark_list_clicks' => 'Click',
        'postmark_list_inbound_messages' => 'Inbound',
    ];

    public function provider(): string
    {
        return 'postmark';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $event = self::EVENTS[$trigger->config['node_type'] ?? ''] ?? null;

        if ($event === null) {
            return null;
        }

        if ($event === 'Inbound') {
            return 'Inbound';
        }

        $stream = trim((string) ($this->nodeConfig($trigger)['stream'] ?? ''));

        return $event.':'.($stream !== '' ? $stream : 'outbound');
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * A webhook Postmark disabled or someone removed is made again; an inbound
     * URL that now points elsewhere is not ours to take back.
     *
     * @throws ConnectorException when the server's inbound webhook now points elsewhere
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $http = $this->postmarkHttp($this->credentialOf($watch));

        if ($watch->resource_key === 'Inbound') {
            $current = (string) $this->ok($http->get(self::API.'/server'))->json('InboundHookUrl');

            if ($current !== '' && ! $this->isOurs($current)) {
                throw new ConnectorException('The Postmark server now sends received email to another address, so its triggers fall back to checking every few minutes. Clear the inbound webhook in Postmark to get instant delivery again.');
            }

            if ($current === '') {
                $this->register($watch);

                return;
            }
        } else {
            $response = $http->get(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

            if ($response->status() === 404 || ($response->successful() && $response->json('Enabled') === false)) {
                $this->register($watch);

                return;
            }

            $this->ok($response);
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $http = $this->postmarkHttp($this->credentialOf($watch));

        if ($watch->resource_key === 'Inbound') {
            $current = (string) $this->ok($http->get(self::API.'/server'))->json('InboundHookUrl');

            if ($this->isOurs($current)) {
                $this->ok($http->put(self::API.'/server', ['InboundHookUrl' => '']));
            }

            return;
        }

        $response = $http->delete(self::API.'/webhooks/'.rawurlencode((string) $watch->external_id));

        if ($response->status() !== 404) {
            $this->ok($response);
        }
    }

    /**
     * Tell Postmark the watch's token URL.
     *
     * @throws WatchRefusedException when the server's inbound webhook is already in use
     */
    private function register(ProviderWatch $watch): void
    {
        $http = $this->postmarkHttp($this->credentialOf($watch));
        [$event, $stream] = array_pad(explode(':', $watch->resource_key, 2), 2, 'outbound');

        if ($event === 'Inbound') {
            $current = (string) $this->ok($http->get(self::API.'/server'))->json('InboundHookUrl');

            if ($current !== '' && ! $this->isOurs($current)) {
                throw new WatchRefusedException('The Postmark server already sends received email to another address ('.(parse_url($current, PHP_URL_HOST) ?: 'unknown').'), and it can have only one. Clear the inbound webhook in Postmark to get instant delivery.');
            }

            $this->registerUrl($watch, fn (string $url) => $this->ok($http->put(self::API.'/server', ['InboundHookUrl' => $url])));
        } else {
            $this->registerUrl($watch, function (string $url) use ($watch, $http, $event, $stream): void {
                $response = $this->ok($http->post(self::API.'/webhooks', ['Url' => $url, 'MessageStream' => $stream, 'Triggers' => [$event => ['Enabled' => true]]]));
                $watch->external_id = (string) $response->json('ID');
            });
        }

        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    private function isOurs(string $url): bool
    {
        return $url !== '' && str_starts_with($url, $this->callbackUrl());
    }

    private function postmarkHttp(ConnectorCredential $credential): PendingRequest
    {
        return Http::withHeaders(['X-Postmark-Server-Token' => $this->tokens->accessToken($credential)])->acceptJson()->timeout(20);
    }
}
