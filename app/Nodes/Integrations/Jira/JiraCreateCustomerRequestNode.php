<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraCreateCustomerRequestNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_create_customer_request';
    }

    public function name(): string
    {
        return 'Jira: Create Customer Request';
    }

    public function description(): string
    {
        return 'Raises a request in a Service Management service desk, optionally for a customer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['service_desk', 'request_type', 'summary'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->siteFields(),

                'service_desk' => Field::dynamic('Service desk', 'jira.service_desks', 'Pick a service desk, or give its id.', '1'),
                'request_type' => Field::dynamic('Request type', 'jira.request_types', 'Pick a request type, or give its id.', '10', uses: ['service_desk']),
                'summary' => Field::text('Summary'),
                'description' => Field::textarea('Description'),
                'on_behalf_of' => Field::dynamic('On behalf of', 'jira.users', 'The customer, by account id or email. Leave empty to raise it as yourself.', 'customer@example.com'),
                'extra_fields' => Field::advanced(Field::json('Other request fields (JSON)', 'Other fields of the request type by id, e.g. {"customfield_10010": "Chrome"}. See Get Request Type Fields.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $values = [
            'summary' => $this->requiredString($config, 'summary', 'The summary'),
            'description' => (string) ($config['description'] ?? ''),
            ...(is_array($config['extra_fields'] ?? null) ? $config['extra_fields'] : []),
        ];

        $created = $this->request($run, $config, 'POST', '/request', [], array_filter([
            'serviceDeskId' => $this->requiredString($config, 'service_desk', 'The service desk'),
            'requestTypeId' => $this->requiredString($config, 'request_type', 'The request type'),
            'requestFieldValues' => $values,
            'raiseOnBehalfOf' => filled($config['on_behalf_of'] ?? null) ? trim((string) $config['on_behalf_of']) : null,
        ], fn (mixed $value): bool => $value !== null), 'servicedesk');

        return $this->shapeRequest($created);
    }
}
