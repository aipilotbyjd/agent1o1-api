<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordEditChannelNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_edit_channel';
    }

    public function name(): string
    {
        return 'Discord: Edit Channel';
    }

    public function description(): string
    {
        return 'Renames a channel or changes its topic, category, slowmode or age restriction. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField(),
                'name' => Field::text('New name'),
                'topic' => Field::text('New topic', 'Up to 1024 characters.'),
                'parent_id' => Field::advanced(Field::text('Move to category', 'The category\'s id.')),
                'nsfw' => Field::advanced(Field::select('Age-restricted', ['unchanged' => 'Leave as it is', 'yes' => 'Yes', 'no' => 'No'], null, 'unchanged')),
                'slowmode_seconds' => Field::advanced(Field::integer('Slowmode (seconds)', 'Up to 21600. 0 turns it off. Leave empty to keep it.', null, 0, 21600)),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $nsfw = (string) ($config['nsfw'] ?? 'unchanged');

        $updated = (array) $this->discord($run, $config, 'PATCH', "/channels/{$channel}", [
            'name' => filled($config['name'] ?? null) ? (string) $config['name'] : null,
            'topic' => filled($config['topic'] ?? null) ? mb_substr((string) $config['topic'], 0, 1024) : null,
            'parent_id' => $this->id($config, 'parent_id', 'The category', required: false),
            'nsfw' => $nsfw === 'yes' ? true : ($nsfw === 'no' ? false : null),
            'rate_limit_per_user' => is_numeric($config['slowmode_seconds'] ?? null) ? (int) $config['slowmode_seconds'] : null,
        ], reason: $config['reason'] ?? null);

        return $this->shapeChannel($updated);
    }
}
