<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KitListTagsNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_tags';
    }

    public function name(): string
    {
        return 'Kit: List Tags';
    }

    public function description(): string
    {
        return 'Lists the account\'s tags.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = $this->listAll($run, $config, '/tags', 'tags', [], 1000);

        return ['tags' => array_map(fn (array $tag): array => ['id' => $tag['id'] ?? null, 'name' => $tag['name'] ?? null, 'created_at' => $tag['created_at'] ?? null], $tags), 'count' => count($tags)];
    }
}
