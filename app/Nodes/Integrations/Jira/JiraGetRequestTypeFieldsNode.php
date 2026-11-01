<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetRequestTypeFieldsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_request_type_fields';
    }

    public function name(): string
    {
        return 'Jira: Get Request Type Fields';
    }

    public function description(): string
    {
        return 'Lists the fields a request type asks for, and which are required.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['service_desk', 'request_type'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'service_desk' => Field::dynamic('Service desk', 'jira.service_desks', 'Pick a service desk, or give its id.', '1'),
                'request_type' => Field::dynamic('Request type', 'jira.request_types', 'Pick a request type, or give its id.', '10', uses: ['service_desk']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->request($run, $config, 'GET', '/servicedesk/'.rawurlencode($this->requiredString($config, 'service_desk', 'The service desk')).'/requesttype/'.rawurlencode($this->requiredString($config, 'request_type', 'The request type')).'/field', [], null, 'servicedesk');

        $fields = array_values(array_map(fn (array $field): array => [
            'id' => $field['fieldId'] ?? null,
            'name' => $field['name'] ?? null,
            'description' => $field['description'] ?? null,
            'required' => $field['required'] ?? false,
            'type' => data_get($field, 'jiraSchema.type'),
            'valid_values' => array_values(array_map(fn (array $value): mixed => $value['label'] ?? $value['value'] ?? null, array_filter((array) ($field['validValues'] ?? []), 'is_array'))),
        ], array_filter((array) ($answer['requestTypeFields'] ?? []), 'is_array')));

        return ['fields' => $fields, 'count' => count($fields)];
    }
}
