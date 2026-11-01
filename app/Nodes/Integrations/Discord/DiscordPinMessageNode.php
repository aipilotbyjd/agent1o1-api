<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordPinMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_pin_message';
    }

    public function name(): string
    {
        return 'Discord: Pin Message';
    }

    public function description(): string
    {
        return 'Pins a message in its channel (up to 50 pins).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id', 'message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField(),
                'message_id' => Field::text('Message id', null, '123456789012345678'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $message = $this->id($config, 'message_id', 'The message');

        $this->discord($run, $config, 'PUT', "/channels/{$channel}/pins/{$message}");

        return ['pinned' => true, 'channel_id' => $channel, 'message_id' => $message];
    }
}
