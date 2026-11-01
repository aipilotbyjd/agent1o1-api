<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraListCustomerRequestsNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_list_customer_requests';
    }

    public function name(): string
    {
        return 'Jira: List Customer Requests';
    }

    public function description(): string
    {
        return 'Lists Service Management requests, optionally of one service desk or status.';
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

                'service_desk' => Field::dynamic('Service desk', 'jira.service_desks', 'Pick a service desk, or give its id.', '1'),
                'status' => Field::select('Status', ['OPEN_REQUESTS' => 'Open', 'CLOSED_REQUESTS' => 'Closed', 'ALL_REQUESTS' => 'All'], default: 'ALL_REQUESTS'),
                'query' => Field::text('Search', 'Only requests with this text.'),
                'limit' => Field::integer('Max results', null, 25, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $requests = $this->listDeskPages($run, $config, '/request', [
            'serviceDeskId' => filled($config['service_desk'] ?? null) ? (string) $config['service_desk'] : null,
            'requestStatus' => (string) ($config['status'] ?? 'ALL_REQUESTS'),
            'searchTerm' => $config['query'] ?? null,
        ], $this->limit($config, 25, 200));

        return ['requests' => array_map($this->shapeRequest(...), $requests), 'count' => count($requests)];
    }
}
