<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class OutlookGetProfileNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_get_profile';
    }

    public function name(): string
    {
        return 'Outlook: Get Profile';
    }

    public function description(): string
    {
        return 'Reads the connected account: its name and email address.';
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
        $me = $this->get($run, '/me', $config, ['$select' => 'id,displayName,mail,userPrincipalName']);

        return ['id' => $me['id'] ?? null, 'name' => $me['displayName'] ?? null, 'email' => $me['mail'] ?? $me['userPrincipalName'] ?? null, 'user_principal_name' => $me['userPrincipalName'] ?? null];
    }
}
