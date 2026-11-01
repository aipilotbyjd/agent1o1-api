<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordAddReactionNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_add_reaction';
    }

    public function name(): string
    {
        return 'Discord: Add Reaction';
    }

    public function description(): string
    {
        return 'Reacts to a message with an emoji as the bot.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id', 'message_id', 'emoji'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField(),
                'message_id' => Field::text('Message id', null, '123456789012345678'),
                'emoji' => Field::text('Emoji', 'The emoji itself (👍), or a server emoji as name:id.', '👍'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $message = $this->id($config, 'message_id', 'The message');

        $this->discord($run, $config, 'PUT', "/channels/{$channel}/messages/{$message}/reactions/{$this->emoji($config)}/@me");

        return ['reacted' => true, 'channel_id' => $channel, 'message_id' => $message, 'emoji' => trim((string) $config['emoji'], ' :')];
    }

    /**
     * The emoji as the URL path wants it.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function emoji(array $config): string
    {
        $emoji = trim((string) ($config['emoji'] ?? ''), " :\t\n");

        return $emoji !== '' ? rawurlencode($emoji) : throw new InvalidArgumentException('The emoji is required.');
    }
}
