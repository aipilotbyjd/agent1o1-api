<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordListPinnedMessagesNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_pinned_messages';
    }

    public function name(): string
    {
        return 'Discord: List Pinned Messages';
    }

    public function description(): string
    {
        return 'Lists the pinned messages of a channel.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $messages = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/channels/{$channel}/pins"), 'is_array'));

        return ['messages' => array_map(fn (array $message): array => $this->shapeMessage($message), $messages), 'count' => count($messages), 'channel_id' => $channel];
    }
}
