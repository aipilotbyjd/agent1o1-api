<?php

namespace App\Nodes\Integrations\Pipedrive;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PipedriveGetMeNode extends AbstractPipedriveNode
{
    public function type(): string
    {
        return 'pipedrive_get_me';
    }

    public function name(): string
    {
        return 'Pipedrive: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected Pipedrive user and company. Checks the connection works.';
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
        $me = (array) ($this->pipedrive($run, $config, 'GET', '/users/me')['data'] ?? []);

        return ['id' => $me['id'] ?? null, 'name' => $me['name'] ?? null, 'email' => $me['email'] ?? null, 'company' => $me['company_name'] ?? null, 'currency' => $me['default_currency'] ?? null, 'admin' => (bool) ($me['is_admin'] ?? false)];
    }
}
