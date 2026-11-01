<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoListSegmentsNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_segments';
    }

    public function name(): string
    {
        return 'Klaviyo: List Segments';
    }

    public function description(): string
    {
        return 'Lists the account\'s segments.';
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
        [$segments] = $this->listAll($run, $config, '/segments', [], 1000, 10);

        return ['segments' => array_map(fn (array $segment): array => ['id' => $segment['id'] ?? null, 'name' => data_get($segment, 'attributes.name'), 'active' => data_get($segment, 'attributes.is_active'), 'created_at' => data_get($segment, 'attributes.created')], $segments), 'count' => count($segments)];
    }
}
