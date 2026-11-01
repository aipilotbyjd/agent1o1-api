<?php

namespace App\Nodes\Integrations\YouTube;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class YouTubeListSubscriptionsNode extends AbstractYouTubeNode
{
    public function type(): string
    {
        return 'google_youtube_list_subscriptions';
    }

    public function name(): string
    {
        return 'YouTube: List Subscriptions';
    }

    public function description(): string
    {
        return 'Lists the channels the connected account is subscribed to.';
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
                'max_results' => Field::integer('Max results', null, 25, 1, 50),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/subscriptions', $config, [
            'part' => 'snippet',
            'mine' => 'true',
            'order' => 'alphabetical',
            'maxResults' => $config['max_results'] ?? 25,
        ]);

        return ['subscriptions' => array_map(fn (array $item): array => $this->subscription($item), (array) ($response['items'] ?? []))];
    }
}
