<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KitGetAccountNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_get_account';
    }

    public function name(): string
    {
        return 'Kit: Get Account';
    }

    public function description(): string
    {
        return 'Gets the Kit account: its name, plan and primary email.';
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
        $account = (array) $this->kit($run, $config, 'GET', '/account');

        return ['name' => data_get($account, 'account.name'), 'plan' => data_get($account, 'account.plan_type'), 'primary_email' => data_get($account, 'account.primary_email_address'), 'user_email' => data_get($account, 'user.email')];
    }
}
