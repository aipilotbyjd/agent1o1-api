<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class TwilioGetCallNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_get_call';
    }

    public function name(): string
    {
        return 'Twilio: Get Call';
    }

    public function description(): string
    {
        return 'Gets a call by its SID: its status, length and price.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['call_sid'],
            'properties' => [
                ...$this->credentialFields(),
                'call_sid' => Field::text('Call SID', 'It starts with CA.', 'CA...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeCall((array) $this->twilio($run, $config, 'GET', '/Calls/'.$this->sid($config, 'call_sid', 'Call SID', 'CA').'.json'));
    }
}
