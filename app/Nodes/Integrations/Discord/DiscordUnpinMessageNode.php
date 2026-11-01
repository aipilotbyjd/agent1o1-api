<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordUnpinMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_unpin_message';
    }

    public function name(): string
    {
        return 'Discord: Unpin Message';
    }

    public function description(): string
    {
        return 'Removes a pin from a message.';
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

        $this->discord($run, $config, 'DELETE', "/channels/{$channel}/pins/{$message}");

        return ['unpinned' => true, 'channel_id' => $channel, 'message_id' => $message];
    }
}
