<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordSendDirectMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_send_direct_message';
    }

    public function name(): string
    {
        return 'Discord: Send Direct Message';
    }

    public function description(): string
    {
        return 'Sends a private message to a person. They must share a server with the bot and allow messages from server members.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => Field::text('Person', 'The person\'s id (a number), or map it from an earlier step.', '123456789012345678'),
                ...$this->messageFields(reply: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $user = $this->id($config, 'user_id', 'The person');
        $body = $this->messageBody($config);
        $dm = (array) $this->discord($run, $config, 'POST', '/users/@me/channels', ['recipient_id' => $user]);
        $message = (array) $this->discord($run, $config, 'POST', "/channels/{$dm['id']}/messages", $body);

        return [...$this->shapeMessage($message), 'user_id' => $user, 'dm_channel_id' => $dm['id']];
    }
}
