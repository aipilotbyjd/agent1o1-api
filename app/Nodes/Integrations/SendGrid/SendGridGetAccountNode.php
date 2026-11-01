<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SendGridGetAccountNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_get_account';
    }

    public function name(): string
    {
        return 'SendGrid: Get Account';
    }

    public function description(): string
    {
        return 'Gets the account type and sender reputation, and checks the API key works.';
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
        $account = (array) $this->sendgrid($run, $config, 'GET', '/user/account');

        return ['type' => $account['type'] ?? null, 'reputation' => $account['reputation'] ?? null];
    }
}
