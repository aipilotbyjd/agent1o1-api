<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordCreateThreadNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_create_thread';
    }

    public function name(): string
    {
        return 'Discord: Create Thread';
    }

    public function description(): string
    {
        return 'Starts a thread in a channel, from a message or on its own, optionally with a first message. In a forum channel it creates a post.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id', 'name'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField('Channel', 'The channel to start the thread in.'),
                'name' => Field::text('Thread name', '1 to 100 characters.', 'Release 2.4 discussion'),
                'message_id' => Field::advanced(Field::text('Start from message', 'The id of a message to start the thread from. Leave empty for a thread on its own.')),
                'content' => Field::textarea('First message', 'Posted in the new thread. Needed for a forum post.'),
                'thread_type' => Field::advanced(Field::select('Kind', ['public' => 'Public', 'private' => 'Private'], 'Only for a thread that does not start from a message.', 'public')),
                'auto_archive_minutes' => Field::advanced(Field::select('Archive after inactivity', ['60' => '1 hour', '1440' => '1 day', '4320' => '3 days', '10080' => '1 week'], null, '1440')),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $channel = $this->id($config, 'channel_id', 'The channel');
        $name = mb_substr($this->requiredString($config, 'name', 'The thread name'), 0, 100);
        $archive = (int) ($config['auto_archive_minutes'] ?? 1440);
        $starter = $this->id($config, 'message_id', 'The message', required: false);
        $content = trim((string) ($config['content'] ?? ''));
        $reason = $config['reason'] ?? null;

        if (mb_strlen($content) > self::MAX_CONTENT) {
            throw new InvalidArgumentException('The first message is over Discord\'s limit of '.self::MAX_CONTENT.' characters.');
        }

        if ($starter !== null) {
            $thread = (array) $this->discord($run, $config, 'POST', "/channels/{$channel}/messages/{$starter}/threads", ['name' => $name, 'auto_archive_duration' => $archive], reason: $reason);
        } else {
            $isForum = $this->channelTypeName(data_get((array) $this->discord($run, $config, 'GET', "/channels/{$channel}"), 'type')) === 'forum';

            if ($isForum && $content === '') {
                throw new InvalidArgumentException('A forum post needs a first message.');
            }

            $thread = (array) $this->discord($run, $config, 'POST', "/channels/{$channel}/threads", $isForum
                ? ['name' => $name, 'auto_archive_duration' => $archive, 'message' => ['content' => $content, 'allowed_mentions' => ['parse' => ['users']]]]
                : ['name' => $name, 'auto_archive_duration' => $archive, 'type' => ($config['thread_type'] ?? 'public') === 'private' ? 12 : 11], reason: $reason);

            if ($isForum) {
                $content = '';
            }
        }

        if ($content !== '') {
            $this->discord($run, $config, 'POST', "/channels/{$thread['id']}/messages", ['content' => $content, 'allowed_mentions' => ['parse' => ['users']]]);
        }

        return $this->shapeChannel($thread);
    }
}
