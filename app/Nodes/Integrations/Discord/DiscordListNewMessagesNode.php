<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\SupportsTriggerMode;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * "New message in a channel". Discord only pushes messages to a bot that holds
 * an open gateway connection, which this app does not keep, so this trigger
 * reads the channel every minute: a new message starts a flow within about a
 * minute. Reading message text needs the bot's "Message Content" intent
 * switched on in the Developer Portal.
 */
class DiscordListNewMessagesNode extends DiscordListMessagesNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'discord_list_new_messages';
    }

    public function name(): string
    {
        return 'Discord: List New Messages';
    }

    public function description(): string
    {
        return 'Lists the latest messages of a channel, newest first. As a trigger it starts the flow for each new message, within about a minute.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField('Channel', 'The channel to watch. Include threads by watching them separately.'),
                'include_bots' => Field::boolean('Include messages from bots', 'Turn off so the bot\'s own posts don\'t start the flow.', false),
                'limit' => Field::integer('Max results', null, 20, 1, 100),
            ],
        ];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'messages';
    }

    public function triggerName(): string
    {
        return 'Discord: New message';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a message is posted in a Discord channel. Checks every minute.';
    }

    /**
     * The widest page, so a busy minute misses nothing.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'include_bots' => $config['include_bots'] ?? false, 'limit' => 100, 'before' => null, 'after' => null];
    }

    public function triggerPollMinutes(): int
    {
        return 1;
    }

    /**
     * Newest first, as Discord lists them.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
