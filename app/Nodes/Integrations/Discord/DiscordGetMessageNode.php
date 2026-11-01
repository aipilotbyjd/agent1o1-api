<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordGetMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_get_message';
    }

    public function name(): string
    {
        return 'Discord: Get Message';
    }

    public function description(): string
    {
        return 'Gets one message by its id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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

        return $this->shapeMessage((array) $this->discord($run, $config, 'GET', "/channels/{$channel}/messages/{$message}"));
    }
}
