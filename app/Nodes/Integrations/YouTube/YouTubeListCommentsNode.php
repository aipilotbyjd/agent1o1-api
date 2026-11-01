<?php

namespace App\Nodes\Integrations\YouTube;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class YouTubeListCommentsNode extends AbstractYouTubeNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'google_youtube_list_comments';
    }

    public function name(): string
    {
        return 'YouTube: List Comments';
    }

    public function description(): string
    {
        return 'Lists the newest top-level comments on a video.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID', 'The ID from the video\'s link (watch?v=...).', 'dQw4w9WgXcQ'),
                'max_results' => Field::integer('Max results', null, 20, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/commentThreads', $config, [
            'part' => 'snippet',
            'videoId' => $config['video_id'],
            'order' => 'time',
            'textFormat' => 'plainText',
            'maxResults' => $config['max_results'] ?? 20,
        ]);

        return ['comments' => array_map(function (array $thread): array {
            $comment = (array) ($thread['snippet']['topLevelComment']['snippet'] ?? []);

            return [
                'comment_id' => $thread['id'] ?? null,
                'author' => $comment['authorDisplayName'] ?? null,
                'author_channel_id' => $comment['authorChannelId']['value'] ?? null,
                'text' => $comment['textDisplay'] ?? null,
                'likes' => (int) ($comment['likeCount'] ?? 0),
                'reply_count' => (int) ($thread['snippet']['totalReplyCount'] ?? 0),
                'published_at' => $comment['publishedAt'] ?? null,
                'video_id' => $comment['videoId'] ?? null,
            ];
        }, (array) ($response['items'] ?? []))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'comments';
    }

    /**
     * A comment's id is its stable id.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['comment_id'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'YouTube: New comment';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone comments on a video.';
    }

    /**
     * Read a wider page than the node's default, so a burst between two polls
     * isn't cut off.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'max_results' => 100];
    }

    /**
     * Newest first, as the list is requested.
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
        return 10;
    }
}
