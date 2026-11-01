<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordListMessagesNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_messages';
    }

    public function name(): string
    {
        return 'Discord: List Messages';
    }

    public function description(): string
    {
        return 'Lists recent messages of a channel, newest first, optionally before or after a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField(),
                'before' => Field::advanced(Field::text('Before message', 'Only messages older than this message id.')),
                'after' => Field::advanced(Field::text('After message', 'Only messages newer than this message id.')),
                'include_bots' => Field::boolean('Include messages from bots', null, true),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');

        $messages = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/channels/{$channel}/messages", query: [
            'limit' => $this->limit($config, 50),
            'before' => $this->id($config, 'before', 'The message', required: false),
            'after' => $this->id($config, 'after', 'The message', required: false),
        ]), 'is_array'));

        $includeBots = filter_var($config['include_bots'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $messages = array_values(array_filter($messages, fn (array $message): bool => $includeBots || ! data_get($message, 'author.bot', false)));

        return ['messages' => array_map(fn (array $message): array => $this->shapeMessage($message), $messages), 'count' => count($messages), 'channel_id' => $channel];
    }
}
