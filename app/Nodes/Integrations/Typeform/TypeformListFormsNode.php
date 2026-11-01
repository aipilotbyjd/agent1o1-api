<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TypeformListFormsNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_list_forms';
    }

    public function name(): string
    {
        return 'Typeform: List Forms';
    }

    public function description(): string
    {
        return 'Lists the forms of the account, newest first, optionally of one workspace or matching some text.';
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
                'workspace_id' => Field::dynamic('Workspace', 'typeform.workspaces', 'Only forms of this workspace.', 'My workspace'),
                'search' => Field::text('Title contains', null, 'survey'),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $limit = $this->limit($config, 50);
        $forms = [];
        $page = 1;

        do {
            $result = (array) $this->typeform($run, $config, 'GET', '/forms', query: [
                'page' => $page,
                'page_size' => min(200, $limit - count($forms)),
                'search' => filled($config['search'] ?? null) ? (string) $config['search'] : null,
                'workspace_id' => filled($config['workspace_id'] ?? null) ? (string) $config['workspace_id'] : null,
                'sort_by' => 'created_at',
                'order_by' => 'desc',
            ]);
            $items = array_values(array_filter((array) ($result['items'] ?? []), 'is_array'));
            array_push($forms, ...$items);
            $page++;
        } while ($items !== [] && $page <= (int) ($result['page_count'] ?? 1) && count($forms) < $limit);

        $forms = array_slice($forms, 0, $limit);

        return ['forms' => array_map($this->shapeForm(...), $forms), 'count' => count($forms), 'total' => $result['total_items'] ?? count($forms)];
    }
}
