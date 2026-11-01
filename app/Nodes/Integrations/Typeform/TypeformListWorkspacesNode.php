<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformListWorkspacesNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_list_workspaces';
    }

    public function name(): string
    {
        return 'Typeform: List Workspaces';
    }

    public function description(): string
    {
        return 'Lists the account\'s workspaces with their form counts.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = (array) $this->typeform($run, $config, 'GET', '/workspaces', query: ['page_size' => $this->limit($config, 50, 200)]);
        $items = array_values(array_filter((array) ($result['items'] ?? []), 'is_array'));

        return ['workspaces' => array_map(fn (array $workspace): array => ['id' => $workspace['id'] ?? null, 'name' => $workspace['name'] ?? null, 'form_count' => data_get($workspace, 'forms.count'), 'shared' => $workspace['shared'] ?? false, 'default' => $workspace['default'] ?? false], $items), 'count' => count($items)];
    }
}
