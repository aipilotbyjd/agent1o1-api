<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class LinearGetMeNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_get_me';
    }

    public function name(): string
    {
        return 'Linear: Get Current User';
    }

    public function description(): string
    {
        return 'Gets the person the Linear account belongs to, and their organization.';
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
        $viewer = (array) ($this->graphql($run, $config, 'query { viewer { id name displayName email active admin url organization { id name urlKey } } }')['viewer'] ?? []);

        return [...$this->shapeUser($viewer), 'organization' => data_get($viewer, 'organization.name'), 'organization_id' => data_get($viewer, 'organization.id'), 'organization_key' => data_get($viewer, 'organization.urlKey')];
    }
}
