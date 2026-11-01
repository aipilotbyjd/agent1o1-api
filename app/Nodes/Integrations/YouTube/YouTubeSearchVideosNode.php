<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeSearchVideosNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_search_videos';
    }

    public function name(): string
    {
        return 'YouTube: Search Videos';
    }

    public function description(): string
    {
        return 'Searches YouTube for videos. Each search uses 100 of your daily YouTube quota units.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search for', null, 'laravel tutorial'),
                'channel_id' => Field::text('Only in channel', 'A channel ID such as UCxxxxxxxxxxxxxxxxxxxxxx. Leave empty to search all of YouTube.'),
                'order' => Field::select('Sort by', [
                    'relevance' => 'Relevance',
                    'date' => 'Newest first',
                    'viewCount' => 'Most viewed',
                    'rating' => 'Highest rated',
                ], null, 'relevance'),
                'published_after' => Field::datetime('Published after'),
                'max_results' => Field::integer('Max results', null, 10, 1, 50),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/search', $config, [
            'part' => 'snippet',
            'type' => 'video',
            'q' => $config['query'],
            'channelId' => $config['channel_id'] ?? null,
            'order' => $config['order'] ?? 'relevance',
            'publishedAfter' => $config['published_after'] ?? null,
            'maxResults' => $config['max_results'] ?? 10,
        ]);

        return ['videos' => array_map(fn (array $item): array => $this->video($item), (array) ($response['items'] ?? []))];
    }
}
