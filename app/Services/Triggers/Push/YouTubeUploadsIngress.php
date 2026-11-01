<?php

namespace App\Services\Triggers\Push;

use App\Contracts\Triggers\ProviderIngress;
use App\Models\Triggers\ProviderWatch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Both halves of YouTube's WebSub feed.
 *
 * - `GET`: the hub checking that we asked for the subscription. Answered with
 *   the hub's challenge, but only for a channel we are watching.
 * - `POST`: an Atom feed with the new video, signed by the hub with the secret
 *   we gave it (HMAC of the body).
 */
class YouTubeUploadsIngress implements ProviderIngress
{
    private const string YT_NAMESPACE = 'http://www.youtube.com/xml/schemas/2015';

    public function provider(): string
    {
        return 'google_youtube';
    }

    public function verify(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            return in_array($request->query('hub_mode', $request->query('hub.mode')), ['subscribe', 'unsubscribe'], true)
                && $this->watchFor($this->channelFromTopic((string) $request->query('hub_topic', $request->query('hub.topic')))) !== null;
        }

        $watch = $this->watchFor($this->feedChannel($request->getContent()));
        $signature = (string) $request->header('X-Hub-Signature');

        if ($watch === null || ! is_string($watch->secret) || ! str_contains($signature, '=')) {
            return false;
        }

        [$algorithm, $hash] = explode('=', $signature, 2);

        return in_array($algorithm, ['sha1', 'sha256'], true)
            && hash_equals(hash_hmac($algorithm, $request->getContent(), $watch->secret), $hash);
    }

    public function handshake(Request $request): array|Response|null
    {
        if (! $request->isMethod('GET')) {
            return null;
        }

        return response((string) $request->query('hub_challenge', $request->query('hub.challenge')), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * One event per published video. A deleted video and a feed with no entry
     * start nothing.
     */
    public function events(Request $request): array
    {
        $feed = $this->parse($request->getContent());

        if ($feed === null) {
            return [];
        }

        $events = [];

        foreach ($feed->entry as $entry) {
            $yt = $entry->children(self::YT_NAMESPACE);
            $videoId = trim((string) $yt->videoId);
            $channelId = trim((string) $yt->channelId);

            if ($videoId === '' || $channelId === '') {
                continue;
            }

            $updated = trim((string) $entry->updated);

            $events[] = [
                // An edit of the same video is a different event, so a title change
                // reaches the trigger (which ignores a video it already ran for).
                'event_id' => $videoId.':'.$updated,
                'event_type' => 'video_published',
                'routing_key' => $channelId,
                'payload' => [
                    'video_id' => $videoId,
                    'title' => trim((string) $entry->title),
                    'published_at' => trim((string) $entry->published),
                    'updated_at' => $updated,
                ],
            ];
        }

        return $events;
    }

    private function watchFor(?string $channelId): ?ProviderWatch
    {
        if ($channelId === null) {
            return null;
        }

        return ProviderWatch::query()->where('provider', $this->provider())->where('external_id', $channelId)->first();
    }

    private function channelFromTopic(string $topic): ?string
    {
        parse_str((string) parse_url($topic, PHP_URL_QUERY), $query);

        $channel = (string) ($query['channel_id'] ?? '');

        return $channel !== '' ? $channel : null;
    }

    /**
     * The channel a feed is about, read from its first entry.
     */
    private function feedChannel(string $body): ?string
    {
        $feed = $this->parse($body);
        $entry = $feed?->entry[0] ?? null;

        if ($entry === null) {
            return null;
        }

        $channel = trim((string) $entry->children(self::YT_NAMESPACE)->channelId);

        return $channel !== '' ? $channel : null;
    }

    /**
     * The feed as XML, without fetching anything it points to.
     */
    private function parse(string $body): ?\SimpleXMLElement
    {
        if (trim($body) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }
}
