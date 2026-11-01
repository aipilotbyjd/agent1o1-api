<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListFieldsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_fields';
    }

    public function name(): string
    {
        return 'Jira: List Fields';
    }

    public function description(): string
    {
        return 'Lists the fields issues can have, including custom fields, with their ids.';
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
                ...$this->siteFields(),

                'query' => Field::text('Name contains', 'Only fields whose name has this text.'),
                'custom_only' => Field::boolean('Only custom fields'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = array_values(array_filter($this->request($run, $config, 'GET', '/field'), 'is_array'));
        $query = strtolower(trim((string) ($config['query'] ?? '')));

        $fields = array_values(array_filter($fields, fn (array $field): bool => (($config['custom_only'] ?? false) !== true || ($field['custom'] ?? false) === true)
            && ($query === '' || str_contains(strtolower((string) ($field['name'] ?? '')), $query))));

        return ['fields' => array_map(fn (array $field): array => ['id' => $field['id'] ?? null, 'name' => $field['name'] ?? null, 'custom' => $field['custom'] ?? false, 'type' => data_get($field, 'schema.type'), 'searchable' => $field['searchable'] ?? null, 'navigable' => $field['navigable'] ?? null], $fields), 'count' => count($fields)];
    }
}
