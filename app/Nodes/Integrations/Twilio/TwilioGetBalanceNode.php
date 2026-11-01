<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TwilioGetBalanceNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_get_balance';
    }

    public function name(): string
    {
        return 'Twilio: Get Balance';
    }

    public function description(): string
    {
        return 'Gets how much credit the account has left.';
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
        $balance = (array) $this->twilio($run, $config, 'GET', '/Balance.json');

        return ['balance' => isset($balance['balance']) ? (float) $balance['balance'] : null, 'currency' => $balance['currency'] ?? null];
    }
}
