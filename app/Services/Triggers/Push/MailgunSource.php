<?php

namespace App\Services\Triggers\Push;

use App\Exceptions\WatchRefusedException;
use App\Models\Connectors\ConnectorCredential;
use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Mailgun triggers through its domain webhooks.
 *
 * Mailgun keeps up to three URLs for each event of a domain, and the account may
 * already use them for something else, so ours is added beside the others and
 * taken out again alone (the event's webhook goes only when ours was the last
 * URL). Mailgun signs deliveries with an account key, but a delivery here only
 * makes the trigger's own poll look now, so the URL's random token (kept hashed)
 * is the check.
 *
 * "Failed for good" and "failed for now" are two webhook events; "any" listens
 * to all of them.
 */
class MailgunSource extends AbstractTokenWatchedSource
{
    private const int MAX_URLS = 3;

    /**
     * The trigger's choice and the webhook events it needs.
     *
     * @var array<string, list<string>>
     */
    private const array EVENTS = [
        'accepted' => ['accepted'],
        'delivered' => ['delivered'],
        'opened' => ['opened'],
        'clicked' => ['clicked'],
        'unsubscribed' => ['unsubscribed'],
        'complained' => ['complained'],
        'permanent_fail' => ['permanent_fail'],
        'temporary_fail' => ['temporary_fail'],
        'all' => ['accepted', 'delivered', 'opened', 'clicked', 'unsubscribed', 'complained', 'permanent_fail', 'temporary_fail'],
    ];

    public function provider(): string
    {
        return 'mailgun';
    }

    protected function nodeTypes(): array
    {
        return ['mailgun_list_events'];
    }

    /**
     * A trigger whose domain looks like one.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->domainFor($trigger) !== null && $this->watchKeyFor($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $event = (string) ($this->nodeConfig($trigger)['event'] ?? 'delivered');

        return isset(self::EVENTS[$event]) ? $event.'@'.$this->domainFor($trigger) : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->register($watch);
    }

    /**
     * Our URL must still be on each event; if Mailgun lost one, put it back.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $http = $this->mailgunHttp($this->credentialOf($watch), $watch);
        $ours = $this->urlOf($watch);

        foreach ($this->eventsOf($watch) as $event) {
            if (! in_array($ours, $this->urls($http, $watch, $event), true)) {
                $this->register($watch);

                return;
            }
        }

        $watch->renew_at = now()->addDay();
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $http = $this->mailgunHttp($this->credentialOf($watch), $watch);

        foreach ($this->eventsOf($watch) as $event) {
            $this->removeUrl($http, $watch, $event);
        }
    }

    /**
     * Add the watch's token URL to each event it needs.
     *
     * @throws WatchRefusedException when an event already has three URLs
     */
    private function register(ProviderWatch $watch): void
    {
        $http = $this->mailgunHttp($this->credentialOf($watch), $watch);
        $previous = $this->urlOf($watch);
        $done = [];

        $this->registerUrl($watch, function (string $url) use ($watch, $http, $previous, &$done): void {
            try {
                foreach ($this->eventsOf($watch) as $event) {
                    $current = $this->urls($http, $watch, $event);
                    $others = array_values(array_filter($current, fn (string $other): bool => $other !== $previous));

                    if (count($others) >= self::MAX_URLS) {
                        throw new WatchRefusedException("Mailgun already sends the \"{$event}\" event to three addresses, which is all it allows. Remove one in Mailgun > Sending > Webhooks to get instant delivery.");
                    }

                    $this->setUrls($http, $watch, $event, [...$others, $url], $current !== []);
                    $done[] = $event;
                }
            } catch (Throwable $exception) {
                foreach ($done as $event) {
                    $this->removeUrl($http, $watch, $event, $url);
                }

                throw $exception;
            }
        });

        $watch->external_id = (string) ($watch->meta['domain'] ?? '');
        $watch->expires_at = null;
        $watch->renew_at = now()->addDay();
    }

    /**
     * The webhook URLs an event has, ours included.
     *
     * @return list<string>
     */
    private function urls(PendingRequest $http, ProviderWatch $watch, string $event): array
    {
        $response = $http->get($this->base($watch)."/v3/domains/{$this->domain($watch)}/webhooks/{$event}");

        if ($response->status() === 404) {
            return [];
        }

        $webhook = (array) $this->ok($response)->json('webhook', []);
        $urls = $webhook['urls'] ?? (isset($webhook['url']) ? [$webhook['url']] : []);

        return array_values(array_filter(array_map('strval', (array) $urls), fn (string $url): bool => $url !== ''));
    }

    /**
     * Make the event's URLs exactly `$urls`: created when it had none, replaced otherwise.
     *
     * @param  list<string>  $urls
     * @param  bool  $exists  whether the event already has a webhook
     */
    private function setUrls(PendingRequest $http, ProviderWatch $watch, string $event, array $urls, bool $exists): void
    {
        $body = implode('&', array_map(fn (string $url): string => 'url='.rawurlencode($url), $urls));
        $base = $this->base($watch)."/v3/domains/{$this->domain($watch)}/webhooks";

        if ($exists) {
            $this->ok($http->withBody($body, 'application/x-www-form-urlencoded')->put("{$base}/{$event}"));

            return;
        }

        $this->ok($http->withBody('id='.rawurlencode($event).'&'.$body, 'application/x-www-form-urlencoded')->post($base));
    }

    /**
     * Take our URL off an event, and the event's webhook with it when ours was the only one.
     */
    private function removeUrl(PendingRequest $http, ProviderWatch $watch, string $event, ?string $url = null): void
    {
        $target = $url ?? $this->urlOf($watch);
        $urls = $this->urls($http, $watch, $event);
        $others = array_values(array_filter($urls, fn (string $other): bool => $other !== $target));

        if (count($others) === count($urls)) {
            return;
        }

        if ($others === []) {
            $response = $http->delete($this->base($watch)."/v3/domains/{$this->domain($watch)}/webhooks/{$event}");

            if ($response->status() !== 404) {
                $this->ok($response);
            }

            return;
        }

        $this->setUrls($http, $watch, $event, $others, true);
    }

    /**
     * @return list<string>
     */
    private function eventsOf(ProviderWatch $watch): array
    {
        return self::EVENTS[explode('@', $watch->resource_key)[0]] ?? [];
    }

    private function domain(ProviderWatch $watch): string
    {
        return (string) ($watch->meta['domain'] ?? explode('@', $watch->resource_key, 2)[1] ?? '');
    }

    private function base(ProviderWatch $watch): string
    {
        return 'https://'.($watch->meta['host'] ?? 'api.mailgun.net');
    }

    /**
     * The URL the watch gave Mailgun, or null before it has a token.
     */
    private function urlOf(ProviderWatch $watch): ?string
    {
        return $watch->secret === null || $watch->secret === '' ? null : $this->callbackUrl().'?token='.$watch->secret;
    }

    /**
     * The domain a trigger sends from: its own setting, else the connection's.
     */
    private function domainFor(Trigger $trigger): ?string
    {
        $credential = $trigger->credential_id === null ? null : ConnectorCredential::query()->find($trigger->credential_id);
        $domain = strtolower(trim((string) (($this->nodeConfig($trigger)['domain'] ?? '') !== '' ? $this->nodeConfig($trigger)['domain'] : ($credential?->data['domain'] ?? ''))));

        return preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $domain) === 1 ? $domain : null;
    }

    private function mailgunHttp(ConnectorCredential $credential, ProviderWatch $watch): PendingRequest
    {
        $watch->meta = [
            ...(array) $watch->meta,
            'host' => strtolower((string) ($credential->data['region'] ?? '')) === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net',
            'domain' => explode('@', $watch->resource_key, 2)[1] ?? '',
        ];

        return Http::withBasicAuth('api', $this->tokens->accessToken($credential))->acceptJson()->timeout(20);
    }
}
