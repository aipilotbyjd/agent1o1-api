<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoGetAccountNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_get_account';
    }

    public function name(): string
    {
        return 'Brevo: Get Account';
    }

    public function description(): string
    {
        return 'Gets the Brevo account: its company, plan and credits.';
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
        $account = (array) $this->brevo($run, $config, 'GET', '/account');

        return [
            'email' => $account['email'] ?? null,
            'company' => data_get($account, 'companyName') ?? data_get($account, 'organization.name'),
            'first_name' => $account['firstName'] ?? null,
            'last_name' => $account['lastName'] ?? null,
            'plans' => array_values(array_map(fn (array $plan): array => ['type' => $plan['type'] ?? null, 'credits' => $plan['credits'] ?? null], array_filter((array) ($account['plan'] ?? []), 'is_array'))),
        ];
    }
}
