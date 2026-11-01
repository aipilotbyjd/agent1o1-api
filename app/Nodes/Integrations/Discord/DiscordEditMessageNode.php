<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordEditMessageNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_edit_message';
    }

    public function name(): string
    {
        return 'Discord: Edit Message';
    }

    public function description(): string
    {
        return 'Changes the text, embed or buttons of a message the bot posted.';
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
                'message_id' => Field::text('Message id', 'From the Send Message step or a trigger.', '123456789012345678'),
                ...$this->messageFields(reply: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $message = $this->id($config, 'message_id', 'The message');
        $body = $this->messageBody($config, allowEmpty: true);
        unset($body['message_reference'], $body['tts']);

        return $this->shapeMessage((array) $this->discord($run, $config, 'PATCH', "/channels/{$channel}/messages/{$message}", $body));
    }
}
