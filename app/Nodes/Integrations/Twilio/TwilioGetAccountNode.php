<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TwilioGetAccountNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_get_account';
    }

    public function name(): string
    {
        return 'Twilio: Get Account';
    }

    public function description(): string
    {
        return 'Gets the account\'s name, status and type (trial or full).';
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
        $account = (array) $this->twilio($run, $config, 'GET', '.json');

        return ['sid' => $account['sid'] ?? null, 'friendly_name' => $account['friendly_name'] ?? null, 'status' => $account['status'] ?? null, 'type' => $account['type'] ?? null, 'date_created' => $this->date($account['date_created'] ?? null)];
    }
}
