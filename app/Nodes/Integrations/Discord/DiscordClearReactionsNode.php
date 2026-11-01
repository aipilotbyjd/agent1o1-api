<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordClearReactionsNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_clear_reactions';
    }

    public function name(): string
    {
        return 'Discord: Clear Reactions';
    }

    public function description(): string
    {
        return 'Removes every reaction from a message, or every reaction of one emoji.';
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
                'emoji' => Field::text('Only this emoji', 'Leave empty to remove them all.', '👍'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $message = $this->id($config, 'message_id', 'The message');
        $emoji = trim((string) ($config['emoji'] ?? ''), " :\t\n");

        $this->discord($run, $config, 'DELETE', "/channels/{$channel}/messages/{$message}/reactions".($emoji !== '' ? '/'.rawurlencode($emoji) : ''));

        return ['cleared' => true, 'channel_id' => $channel, 'message_id' => $message, 'emoji' => $emoji !== '' ? $emoji : null];
    }
}
