<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordCreateChannelNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_create_channel';
    }

    public function name(): string
    {
        return 'Discord: Create Channel';
    }

    public function description(): string
    {
        return 'Creates a text, voice, announcement, forum or stage channel, or a category, in a server.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'name' => Field::text('Name', 'Lower case, no spaces: Discord adjusts it for text channels.', 'project-updates'),
                'channel_type' => Field::select('Kind', ['text' => 'Text', 'voice' => 'Voice', 'category' => 'Category', 'announcement' => 'Announcement', 'forum' => 'Forum', 'stage' => 'Stage'], null, 'text'),
                'topic' => Field::text('Topic', 'Up to 1024 characters.'),
                'parent_id' => Field::advanced(Field::text('Category id', 'Put it under this category.')),
                'nsfw' => Field::advanced(Field::boolean('Age-restricted')),
                'slowmode_seconds' => Field::advanced(Field::integer('Slowmode (seconds)', 'Seconds a person must wait between messages, up to 21600.', null, 0, 21600)),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $types = ['text' => 0, 'voice' => 2, 'category' => 4, 'announcement' => 5, 'stage' => 13, 'forum' => 15];
        $type = (string) ($config['channel_type'] ?? 'text');

        if (! isset($types[$type])) {
            throw new InvalidArgumentException("\"{$type}\" is not a kind of channel. Use text, voice, category, announcement, forum or stage.");
        }

        $channel = (array) $this->discord($run, $config, 'POST', "/guilds/{$guild}/channels", [
            'name' => $this->requiredString($config, 'name', 'The name'),
            'type' => $types[$type],
            'topic' => filled($config['topic'] ?? null) ? mb_substr((string) $config['topic'], 0, 1024) : null,
            'parent_id' => $this->id($config, 'parent_id', 'The category', required: false),
            'nsfw' => filter_var($config['nsfw'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: null,
            'rate_limit_per_user' => is_numeric($config['slowmode_seconds'] ?? null) && (int) $config['slowmode_seconds'] > 0 ? (int) $config['slowmode_seconds'] : null,
        ], reason: $config['reason'] ?? null);

        return $this->shapeChannel($channel);
    }
}
