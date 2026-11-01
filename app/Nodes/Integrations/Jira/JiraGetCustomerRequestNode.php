<?php

namespace App\Nodes\Integrations\Jira;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class JiraGetCustomerRequestNode extends AbstractJiraNode
{
    public function type(): string
    {
        return 'jira_get_customer_request';
    }

    public function name(): string
    {
        return 'Jira: Get Customer Request';
    }

    public function description(): string
    {
        return 'Gets one Service Management request by its issue key, with its status and customer portal link.';
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
        $request = $this->request($run, $config, 'GET', '/request/'.rawurlencode($this->issueKey($config)), [], null, 'servicedesk');

        return [
            ...$this->shapeRequest($request),
            'summary' => collect((array) ($request['requestFieldValues'] ?? []))->firstWhere('fieldId', 'summary')['value'] ?? null,
        ];
    }
}
