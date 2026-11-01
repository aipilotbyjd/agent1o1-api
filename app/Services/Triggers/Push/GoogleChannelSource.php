<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Google's push channels, as used by Drive and Calendar: a channel with our id,
 * a secret token and a callback URL. Google posts an empty notification
 * ("something changed") and the trigger's own poll fetches what.
 *
 * Channels cannot be extended, so renewal opens a new one and then closes the
 * old one: there is no gap in which a change could be missed.
 */
abstract class GoogleChannelSource extends AbstractWatchedSource
{
    /**
     * The longest we ask a channel to live, in days. Google may shorten it.
     */
    private const int LIFETIME_DAYS = 6;

    /**
     * The API's base URL, without a trailing slash.
     */
    abstract protected function api(): string;

    /**
     * Open the channel on the resource and return Google's answer.
     *
     * @param  array<string, mixed>  $channel  id, type, address, token, expiration
     * @return array<string, mixed>
     */
    abstract protected function openChannel(PendingRequest $http, ProviderWatch $watch, array $channel): array;

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->open($watch);
    }

    /**
     * New channel first, then close the old one.
     */
    public function renewWatch(ProviderWatch $watch): void
    {
        $oldId = $watch->external_id;
        $oldResource = $watch->meta['resource_id'] ?? null;

        $this->open($watch);

        if ($oldId !== null && $oldResource !== null) {
            $this->stop($watch, $oldId, (string) $oldResource);
        }
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        if ($watch->external_id !== null && isset($watch->meta['resource_id'])) {
            $this->stop($watch, $watch->external_id, (string) $watch->meta['resource_id']);
        }
    }

    /**
     * Google names the channel in every notification.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    private function open(ProviderWatch $watch): void
    {
        $http = $this->http($this->credentialOf($watch));
        $secret = $this->newSecret();
        $channelId = (string) Str::uuid();

        $body = $this->openChannel($http, $watch, [
            'id' => $channelId,
            'type' => 'web_hook',
            'address' => $this->callbackUrl(),
            'token' => $secret,
            'expiration' => now()->addDays(self::LIFETIME_DAYS)->getTimestampMs(),
        ]);

        $expires = isset($body['expiration']) ? Carbon::createFromTimestampMs((int) $body['expiration']) : now()->addDays(self::LIFETIME_DAYS);

        $watch->external_id = $channelId;
        $watch->secret = $secret;
        $watch->meta = [...(array) $watch->meta, 'resource_id' => $body['resourceId'] ?? null];
        $watch->expires_at = $expires;
        $watch->renew_at = max(now()->addHour(), $expires->copy()->subDay());
    }

    private function stop(ProviderWatch $watch, string $channelId, string $resourceId): void
    {
        try {
            $this->http($this->credentialOf($watch))->post($this->api().'/channels/stop', ['id' => $channelId, 'resourceId' => $resourceId]);
        } catch (\Throwable) {
            // Already gone or expired: nothing to close.
        }
    }
}
