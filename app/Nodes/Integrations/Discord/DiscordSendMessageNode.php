<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordSendMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_send_message';
    }

    public function name(): string
    {
        return 'Discord: Send Message';
    }

    public function description(): string
    {
        return 'Posts a message to a channel as the bot, with optional embed, buttons and a reply.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField('Channel', 'The channel to post in. Pick one, or map its id.'),
                ...$this->messageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');

        return $this->shapeMessage((array) $this->discord($run, $config, 'POST', "/channels/{$channel}/messages", $this->messageBody($config)));
    }
}
