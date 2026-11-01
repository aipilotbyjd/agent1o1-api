<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordDeleteMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_delete_message';
    }

    public function name(): string
    {
        return 'Discord: Delete Message';
    }

    public function description(): string
    {
        return 'Deletes a message. The bot can delete its own, and others where it may manage messages.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $message = $this->id($config, 'message_id', 'The message');

        $this->discord($run, $config, 'DELETE', "/channels/{$channel}/messages/{$message}", reason: $config['reason'] ?? null);

        return ['deleted' => true, 'channel_id' => $channel, 'message_id' => $message];
    }
}
