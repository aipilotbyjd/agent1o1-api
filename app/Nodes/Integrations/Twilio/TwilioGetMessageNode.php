<?php

namespace App\Nodes\Integrations\Twilio;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class TwilioGetMessageNode extends AbstractTwilioNode
{
    public function type(): string
    {
        return 'twilio_get_message';
    }

    public function name(): string
    {
        return 'Twilio: Get Message';
    }

    public function description(): string
    {
        return 'Gets a message by its SID: its status, price and any error.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_sid'],
            'properties' => [
                ...$this->credentialFields(),
                'message_sid' => Field::text('Message SID', 'It starts with SM or MM.', 'SM...'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sid = trim((string) ($config['message_sid'] ?? ''));

        if (preg_match('/^(SM|MM)[0-9a-f]{32}$/i', $sid) !== 1) {
            throw new InvalidArgumentException('The message SID starts with SM or MM and has 34 characters.');
        }

        return $this->shapeMessage((array) $this->twilio($run, $config, 'GET', "/Messages/{$sid}.json"));
    }
}
