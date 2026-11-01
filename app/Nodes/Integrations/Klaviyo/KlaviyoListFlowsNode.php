<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoListFlowsNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_flows';
    }

    public function name(): string
    {
        return 'Klaviyo: List Flows';
    }

    public function description(): string
    {
        return 'Lists the account\'s flows (automations) with their status.';
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
        [$flows] = $this->listAll($run, $config, '/flows', [], 1000, 50);

        return ['flows' => array_map(fn (array $flow): array => ['id' => $flow['id'] ?? null, 'name' => data_get($flow, 'attributes.name'), 'status' => data_get($flow, 'attributes.status'), 'archived' => data_get($flow, 'attributes.archived'), 'trigger_type' => data_get($flow, 'attributes.trigger_type'), 'created_at' => data_get($flow, 'attributes.created')], $flows), 'count' => count($flows)];
    }
}
