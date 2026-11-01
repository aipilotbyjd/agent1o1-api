<?php

namespace App\Nodes\Integrations\HubSpot;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class HubSpotListOwnersNode extends AbstractHubSpotNode
{
    public function type(): string
    {
        return 'hubspot_list_owners';
    }

    public function name(): string
    {
        return 'HubSpot: List Owners';
    }

    public function description(): string
    {
        return 'Lists the HubSpot users who can own records, with their ids.';
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
        $owners = [];
        $after = null;

        do {
            $page = $this->hubspot($run, $config, 'GET', '/crm/v3/owners', ['limit' => 100, 'after' => $after]);
            array_push($owners, ...array_values(array_filter((array) ($page['results'] ?? []), 'is_array')));
            $after = data_get($page, 'paging.next.after');
        } while ($after !== null && count($owners) < 1000);

        return ['owners' => array_map(fn (array $owner): array => ['id' => isset($owner['id']) ? (string) $owner['id'] : null, 'user_id' => $owner['userId'] ?? null, 'email' => $owner['email'] ?? null, 'name' => trim(($owner['firstName'] ?? '').' '.($owner['lastName'] ?? '')) ?: null], $owners), 'count' => count($owners)];
    }
}
