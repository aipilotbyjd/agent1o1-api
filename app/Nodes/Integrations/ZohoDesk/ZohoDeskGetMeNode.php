<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoDeskGetMeNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_get_me';
    }

    public function name(): string
    {
        return 'Zoho Desk: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected agent and checks the connection works.';
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
        $me = $this->desk($run, $config, 'GET', '/myinfo');

        return ['id' => isset($me['id']) ? (string) $me['id'] : null, 'name' => trim(($me['firstName'] ?? '').' '.($me['lastName'] ?? '')) ?: null, 'email' => $me['emailId'] ?? null, 'role' => $me['roleName'] ?? null];
    }
}
