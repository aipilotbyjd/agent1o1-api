<?php

namespace App\Nodes\Integrations\ActiveCampaign;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ActiveCampaignListTagsNode extends AbstractActiveCampaignNode
{
    public function type(): string
    {
        return 'activecampaign_list_tags';
    }

    public function name(): string
    {
        return 'ActiveCampaign: List Tags';
    }

    public function description(): string
    {
        return 'Lists the account\'s tags, optionally matching a search.';
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
                'search' => Field::text('Name contains'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = $this->listAll($run, $config, '/tags', 'tags', ['search' => filled($config['search'] ?? null) ? (string) $config['search'] : null], 1000);

        return ['tags' => array_map(fn (array $tag): array => ['id' => (int) $tag['id'], 'name' => $tag['tag'] ?? null, 'type' => $tag['tagType'] ?? null, 'contact_count' => isset($tag['subscriber_count']) ? (int) $tag['subscriber_count'] : null], $tags), 'count' => count($tags)];
    }
}
