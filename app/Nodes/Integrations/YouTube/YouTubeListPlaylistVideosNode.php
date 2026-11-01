<?php

namespace App\Nodes\Integrations\YouTube;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class YouTubeListPlaylistVideosNode extends AbstractYouTubeNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_youtube_list_playlist_videos';
    }

    public function name(): string
    {
        return 'YouTube: List Playlist Videos';
    }

    public function description(): string
    {
        return 'Lists the videos in a playlist, or a channel\'s uploads. Cheap on quota: 1 unit per call.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'playlist_id' => Field::dynamic('Playlist', 'google_youtube.playlists', 'One of your playlists, or any playlist ID.', 'PLxxxxxxxxxxxxxxxx'),
                'channel_id' => Field::text('Or a channel\'s uploads', 'A channel ID such as UCxxxxxxxxxxxxxxxxxxxxxx. Used when no playlist is set.'),
                'max_results' => Field::integer('Max results', null, 25, 1, 50),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $playlistId = trim((string) ($config['playlist_id'] ?? ''));
        $channelId = trim((string) ($config['channel_id'] ?? ''));

        if ($playlistId === '' && $channelId === '') {
            throw new InvalidArgumentException('YouTube: choose a playlist or enter a channel ID.');
        }

        $response = $this->get($run, '/playlistItems', $config, [
            'part' => 'snippet,contentDetails',
            'playlistId' => $playlistId !== '' ? $playlistId : $this->uploadsPlaylistId($channelId),
            'maxResults' => $config['max_results'] ?? 25,
        ]);

        return ['videos' => array_map(fn (array $item): array => $this->video($item), (array) ($response['items'] ?? []))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'videos';
    }

    /**
     * A video's id is its stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['video_id'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'YouTube: New video';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a video is added to a playlist or a channel uploads one.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 50];
    }

    /**
     * Newest first, as playlists and uploads are listed.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
