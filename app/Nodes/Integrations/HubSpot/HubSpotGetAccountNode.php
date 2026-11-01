<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class HubSpotGetAccountNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_get_account';
    }

    public function name(): string
    {
        return 'HubSpot: Get Account';
    }

    public function description(): string
    {
        return 'Reads the connected HubSpot account: its portal id, currency and time zone. Checks the connection works.';
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
        $account = $this->hubspot($run, $config, 'GET', '/account-info/v3/details');

        return ['portal_id' => $account['portalId'] ?? null, 'currency' => $account['companyCurrency'] ?? null, 'time_zone' => $account['timeZone'] ?? null, 'data_hosting_location' => $account['dataHostingLocation'] ?? null];
    }
}
