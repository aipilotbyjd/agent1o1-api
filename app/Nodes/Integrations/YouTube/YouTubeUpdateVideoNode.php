<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class YouTubeUpdateVideoNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_update_video';
    }

    public function name(): string
    {
        return 'YouTube: Update Video';
    }

    public function description(): string
    {
        return "Changes a video's title, description, tags or visibility. Anything left empty stays as it is.";
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['video_id'],
            'properties' => [
                ...$this->credentialFields(),
                'video_id' => Field::text('Video ID or link', 'A video on the connected account\'s channel.', 'dQw4w9WgXcQ'),
                'title' => Field::text('New title'),
                'description' => Field::textarea('New description'),
                'tags' => Field::list('Tags', 'Replaces the video\'s tags. Leave empty to keep them.', 'tutorial'),
                'privacy' => $this->privacyField(null, 'Leave empty to keep the current visibility.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->videoIdFromLink((string) $config['video_id']);

        $existing = $this->get($run, '/videos', $config, ['part' => 'snippet,status', 'id' => $id])['items'][0] ?? null;

        if (! is_array($existing)) {
            throw new RuntimeException("YouTube: no video found with the ID \"{$id}\".");
        }

        // videos.update resets every writable field it is not sent, so the current
        // values go back with the change.
        $snippet = array_intersect_key((array) $existing['snippet'], array_flip(['title', 'description', 'tags', 'categoryId', 'defaultLanguage']));
        $status = array_intersect_key((array) ($existing['status'] ?? []), array_flip(['privacyStatus', 'embeddable', 'license', 'publicStatsViewable', 'selfDeclaredMadeForKids']));

        $title = trim((string) ($config['title'] ?? ''));
        $description = trim((string) ($config['description'] ?? ''));
        $tags = array_values(array_filter(array_map('trim', array_map('strval', (array) ($config['tags'] ?? [])))));
        $privacy = trim((string) ($config['privacy'] ?? ''));

        if ($title !== '') {
            $snippet['title'] = $title;
        }

        if ($description !== '') {
            $snippet['description'] = $description;
        }

        if ($tags !== []) {
            $snippet['tags'] = $tags;
        }

        if ($privacy !== '') {
            $status['privacyStatus'] = $privacy;
        }

        return $this->video($this->put($run, '/videos?part=snippet,status', $config, ['id' => $id, 'snippet' => $snippet, 'status' => $status]));
    }
}
