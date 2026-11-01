<?php

namespace App\Nodes\Integrations\YouTube;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\CallsGoogleJsonApi;
use App\Nodes\Support\Field;
use RuntimeException;

/**
 * Shared request handling and shaping for the YouTube node family: search,
 * videos, channels, playlists, comments and subscriptions.
 */
abstract class AbstractYouTubeNode implements DeclaresEffect, NodeContract
{
    use CallsGoogleJsonApi;

    public function category(): string
    {
        return 'google_youtube';
    }

    protected function baseUrl(): string
    {
        return 'https://www.googleapis.com/youtube/v3';
    }

    protected function appName(): string
    {
        return 'YouTube';
    }

    /**
     * A video's id from a search result, a playlist item or a video resource.
     *
     * @param  array<string, mixed>  $item
     */
    protected function videoId(array $item): ?string
    {
        $id = $item['id'] ?? null;

        return $item['contentDetails']['videoId']
            ?? $item['snippet']['resourceId']['videoId']
            ?? (is_array($id) ? ($id['videoId'] ?? null) : $id);
    }

    /**
     * A video in the flat shape the nodes return.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function video(array $item): array
    {
        $id = $this->videoId($item);
        $snippet = (array) ($item['snippet'] ?? []);

        return array_filter([
            'video_id' => $id,
            'title' => $snippet['title'] ?? null,
            'description' => $snippet['description'] ?? null,
            'channel_id' => $snippet['channelId'] ?? $snippet['videoOwnerChannelId'] ?? null,
            'channel_title' => $snippet['channelTitle'] ?? null,
            'published_at' => $item['contentDetails']['videoPublishedAt'] ?? $snippet['publishedAt'] ?? null,
            'thumbnail' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null,
            'url' => $id !== null ? "https://www.youtube.com/watch?v={$id}" : null,
            'duration' => $item['contentDetails']['duration'] ?? null,
            'views' => isset($item['statistics']['viewCount']) ? (int) $item['statistics']['viewCount'] : null,
            'likes' => isset($item['statistics']['likeCount']) ? (int) $item['statistics']['likeCount'] : null,
            'comments' => isset($item['statistics']['commentCount']) ? (int) $item['statistics']['commentCount'] : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The "uploads" playlist of a channel, which lists every public video in
     * upload order. A channel ID `UCxxxx` has the uploads playlist `UUxxxx`.
     */
    protected function uploadsPlaylistId(string $channelId): string
    {
        return str_starts_with($channelId, 'UC') ? 'UU'.substr($channelId, 2) : $channelId;
    }

    /**
     * The video ID from a watch, share, shorts or embed link, or the ID itself.
     */
    protected function videoIdFromLink(string $value): string
    {
        $value = trim($value);

        if (preg_match('~(?:v=|youtu\.be/|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', $value, $match) === 1) {
            return $match[1];
        }

        return $value;
    }

    /**
     * A playlist in the flat shape the nodes return.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function playlist(array $item): array
    {
        $id = $item['id'] ?? null;
        $snippet = (array) ($item['snippet'] ?? []);

        return array_filter([
            'playlist_id' => $id,
            'title' => $snippet['title'] ?? null,
            'description' => $snippet['description'] ?? null,
            'channel_id' => $snippet['channelId'] ?? null,
            'channel_title' => $snippet['channelTitle'] ?? null,
            'published_at' => $snippet['publishedAt'] ?? null,
            'privacy' => $item['status']['privacyStatus'] ?? null,
            'video_count' => isset($item['contentDetails']['itemCount']) ? (int) $item['contentDetails']['itemCount'] : null,
            'url' => $id !== null ? "https://www.youtube.com/playlist?list={$id}" : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * A subscription in the flat shape the nodes return. `$subscriberSide` is
     * true for the people who subscribed to the account's channel, where the
     * channel of interest is the subscriber rather than the one subscribed to.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function subscription(array $item, bool $subscriberSide = false): array
    {
        $snippet = (array) ($item['snippet'] ?? []);
        $subscriber = (array) ($item['subscriberSnippet'] ?? []);
        $channelId = $subscriberSide ? ($subscriber['channelId'] ?? null) : ($snippet['resourceId']['channelId'] ?? null);

        return array_filter([
            'subscription_id' => $item['id'] ?? null,
            'channel_id' => $channelId,
            'title' => $subscriberSide ? ($subscriber['title'] ?? null) : ($snippet['title'] ?? null),
            'description' => $subscriberSide ? ($subscriber['description'] ?? null) : ($snippet['description'] ?? null),
            'thumbnail' => ($subscriberSide ? ($subscriber['thumbnails']['default']['url'] ?? null) : ($snippet['thumbnails']['default']['url'] ?? null)),
            'subscribed_at' => $snippet['publishedAt'] ?? null,
            'url' => $channelId !== null ? "https://www.youtube.com/channel/{$channelId}" : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Who can see a video or a playlist.
     *
     * @return array<string, mixed>
     */
    protected function privacyField(?string $default = null, ?string $description = null): array
    {
        return Field::select('Visibility', [
            'public' => 'Public',
            'unlisted' => 'Unlisted (anyone with the link)',
            'private' => 'Private',
        ], $description, $default);
    }

    /**
     * A channel's ID from an ID or an @handle.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException when the handle matches no channel
     */
    protected function resolveChannelId(Run $run, array $config, string $channel): string
    {
        $channel = trim($channel);

        if (! str_starts_with($channel, '@')) {
            return $channel;
        }

        $id = $this->get($run, '/channels', $config, ['part' => 'id', 'forHandle' => $channel])['items'][0]['id'] ?? null;

        if (! is_string($id)) {
            throw new RuntimeException("YouTube: no channel found with the handle \"{$channel}\".");
        }

        return $id;
    }
}
