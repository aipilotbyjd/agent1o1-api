<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TwilioListVerifyServicesNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_list_verify_services';
    }

    public function name(): string
    {
        return 'Twilio: List Verify Services';
    }

    public function description(): string
    {
        return 'Lists the Verify services of the account.';
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
        $services = array_values(array_filter((array) data_get((array) $this->twilio($run, $config, 'GET', '/v2/Services', ['PageSize' => 100], 'verify'), 'services', []), 'is_array'));

        return ['services' => array_map(fn (array $service): array => ['sid' => $service['sid'] ?? null, 'friendly_name' => $service['friendly_name'] ?? null, 'code_length' => $service['code_length'] ?? null], $services), 'count' => count($services)];
    }
}
