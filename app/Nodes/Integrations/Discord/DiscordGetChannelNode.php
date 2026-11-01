<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class DiscordGetChannelNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_get_channel';
    }

    public function name(): string
    {
        return 'Discord: Get Channel';
    }

    public function description(): string
    {
        return 'Gets a channel or thread: its name, kind, topic and settings.';
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

        return $this->shapeChannel((array) $this->discord($run, $config, 'GET', "/channels/{$channel}"));
    }
}
