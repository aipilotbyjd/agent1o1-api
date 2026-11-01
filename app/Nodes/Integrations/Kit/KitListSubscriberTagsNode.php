<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitListSubscriberTagsNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_subscriber_tags';
    }

    public function name(): string
    {
        return 'Kit: List Subscriber Tags';
    }

    public function description(): string
    {
        return 'Lists the tags a subscriber has.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subscriber'],
            'properties' => [
                ...$this->credentialFields(),
                'subscriber' => Field::text('Subscriber', 'Their email address or Kit id.', 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->subscriberId($run, $config);
        $tags = $this->listAll($run, $config, "/subscribers/{$id}/tags", 'tags', [], 1000);

        return ['tags' => array_map(fn (array $tag): array => ['id' => $tag['id'] ?? null, 'name' => $tag['name'] ?? null, 'tagged_at' => $tag['created_at'] ?? null], $tags), 'count' => count($tags)];
    }
}
