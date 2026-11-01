<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoDeskListAgentsNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_list_agents';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Agents';
    }

    public function description(): string
    {
        return 'Lists the support agents, to assign tickets to.';
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
        $agents = $this->listAll($run, $config, '/agents', ['status' => 'ACTIVE'], 200);

        return ['agents' => array_map(fn (array $agent): array => ['id' => isset($agent['id']) ? (string) $agent['id'] : null, 'name' => trim(($agent['firstName'] ?? '').' '.($agent['lastName'] ?? '')) ?: ($agent['name'] ?? null), 'email' => $agent['emailId'] ?? null], $agents), 'count' => count($agents)];
    }
}
