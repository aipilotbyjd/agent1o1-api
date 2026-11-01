<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetEditMetadataNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_edit_metadata';
    }

    public function name(): string
    {
        return 'Jira: Get Edit Metadata';
    }

    public function description(): string
    {
        return 'Lists the fields an issue can be edited with, and the values each allows.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['issue'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),
                'issue' => Field::text('Issue', 'The issue key, e.g. APP-123.', 'APP-123'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = [];

        foreach ($this->editMeta($run, $config, $this->issueKey($config)) as $id => $field) {
            $fields[] = [
                'id' => (string) $id,
                'name' => $field['name'] ?? null,
                'required' => $field['required'] ?? false,
                'type' => data_get($field, 'schema.type'),
                'operations' => array_values((array) ($field['operations'] ?? [])),
                'allowed_values' => array_values(array_slice(array_map(fn (mixed $value): mixed => is_array($value) ? ($value['name'] ?? $value['value'] ?? $value['id'] ?? null) : $value, (array) ($field['allowedValues'] ?? [])), 0, 50)),
            ];
        }

        return ['fields' => $fields, 'count' => count($fields)];
    }
}
