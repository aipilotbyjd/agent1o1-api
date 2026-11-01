<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;
use Illuminate\Support\Facades\Http;

/**
 * "New video" on a YouTube channel through YouTube's WebSub feed: the hub calls
 * us the moment the channel publishes, so nothing has to read the channel
 * every few minutes. No Google Cloud setup and no API quota.
 *
 * Only a channel's uploads are covered; a trigger on a playlist keeps polling.
 * The feed says a video appeared and the trigger's own poll fetches it (one
 * quota unit), so push and poll can never disagree. A subscription lasts
 * `lease_seconds`; it is renewed a day before it ends.
 */
class YouTubeUploadsSource extends AbstractWatchedSource
{
    private const string FEED = 'https://www.youtube.com/xml/feeds/videos.xml?channel_id=';

    public function provider(): string
    {
        return 'google_youtube';
    }

    protected function nodeTypes(): array
    {
        return ['google_youtube_list_playlist_videos'];
    }

    /**
     * A channel's uploads only: a playlist, a template or no channel is left to polling.
     */
    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && $this->channelOf($trigger) !== null;
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $channel = $this->channelOf($trigger);

        return $channel === null ? null : 'channel:'.$channel;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        $this->subscribe($watch, 'subscribe');
    }

    public function renewWatch(ProviderWatch $watch): void
    {
        $this->subscribe($watch, 'subscribe');
    }

    public function deleteWatch(ProviderWatch $watch): void
    {
        $this->send($watch, 'unsubscribe');
    }

    /**
     * The hub's notifications name the channel.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        return $watch?->external_id;
    }

    /**
     * The feed address of a channel.
     */
    public static function topicFor(string $channelId): string
    {
        return self::FEED.$channelId;
    }

    private function channelOf(Trigger $trigger): ?string
    {
        $config = $this->nodeConfig($trigger);
        $channel = trim((string) ($config['channel_id'] ?? ''));

        if (trim((string) ($config['playlist_id'] ?? '')) !== '' || preg_match('/^UC[A-Za-z0-9_-]{22}$/', $channel) !== 1) {
            return null;
        }

        return $channel;
    }

    private function subscribe(ProviderWatch $watch, string $mode): void
    {
        $watch->external_id = substr($watch->resource_key, strlen('channel:'));
        $watch->secret ??= $this->newSecret();

        $this->send($watch, $mode);

        $lease = (int) config('triggers.push.google_youtube.lease_seconds');

        $watch->expires_at = now()->addSeconds($lease);
        $watch->renew_at = now()->addSeconds(max(3600, $lease - 86400));
    }

    /**
     * Ask the hub. It answers "accepted" and then calls our address to confirm
     * (see YouTubeUploadsIngress).
     */
    private function send(ProviderWatch $watch, string $mode): void
    {
        Http::asForm()->timeout(20)->post((string) config('triggers.push.google_youtube.hub_url'), [
            'hub.mode' => $mode,
            'hub.topic' => self::topicFor(substr($watch->resource_key, strlen('channel:'))),
            'hub.callback' => $this->callbackUrl(),
            'hub.secret' => $watch->secret,
            'hub.lease_seconds' => (int) config('triggers.push.google_youtube.lease_seconds'),
            'hub.verify' => 'async',
        ])->throw();
    }
}
