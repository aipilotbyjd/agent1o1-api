<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordDeleteChannelNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_delete_channel';
    }

    public function name(): string
    {
        return 'Discord: Delete Channel';
    }

    public function description(): string
    {
        return 'Deletes a channel, category or thread, with everything in it. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField(),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');

        $this->discord($run, $config, 'DELETE', "/channels/{$channel}", reason: $config['reason'] ?? null);

        return ['deleted' => true, 'channel_id' => $channel];
    }
}
