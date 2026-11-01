<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordListReactionsNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_list_reactions';
    }

    public function name(): string
    {
        return 'Discord: List Reactions';
    }

    public function description(): string
    {
        return 'Lists the people who reacted to a message with an emoji.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
                'limit' => Field::integer('Max results', null, 100, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $message = $this->id($config, 'message_id', 'The message');

        $users = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/channels/{$channel}/messages/{$message}/reactions/{$this->emoji($config)}", query: ['limit' => $this->limit($config, 100)]), 'is_array'));

        return ['users' => array_map(fn (array $user): array => $this->shapeUser($user), $users), 'count' => count($users), 'emoji' => trim((string) $config['emoji'], ' :')];
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
