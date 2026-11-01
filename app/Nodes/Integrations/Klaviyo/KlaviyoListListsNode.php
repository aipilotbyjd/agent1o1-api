<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoListListsNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_lists';
    }

    public function name(): string
    {
        return 'Klaviyo: List Lists';
    }

    public function description(): string
    {
        return 'Lists the account\'s lists.';
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
        [$lists] = $this->listAll($run, $config, '/lists', [], 1000, 10);

        return ['lists' => array_map($this->shapeList(...), $lists), 'count' => count($lists)];
    }
}
