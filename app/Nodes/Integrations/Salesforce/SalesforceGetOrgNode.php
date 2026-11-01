<?php

namespace App\Nodes\Integrations\Salesforce;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Services\Connectors\CrmAccessTokens;

class SalesforceGetOrgNode extends AbstractSalesforceNode
{
    public function type(): string
    {
        return 'salesforce_get_org';
    }

    public function name(): string
    {
        return 'Salesforce: Get Org';
    }

    public function description(): string
    {
        return 'Reads the connected org\'s API usage limits and checks the connection works.';
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
        $limits = $this->salesforce($run, $config, 'GET', '/limits');
        $login = app(CrmAccessTokens::class)->salesforce($this->resolveCredentialData($run, $config));

        return ['instance' => $login['instance'], 'api_requests_max' => data_get($limits, 'DailyApiRequests.Max'), 'api_requests_left' => data_get($limits, 'DailyApiRequests.Remaining')];
    }
}
