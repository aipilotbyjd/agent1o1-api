<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordAddThreadMemberNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_add_thread_member';
    }

    public function name(): string
    {
        return 'Discord: Add Person to Thread';
    }

    public function description(): string
    {
        return 'Adds a person to a thread.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel_id', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'channel_id' => $this->channelField('Thread', 'The thread\'s id.'),
                'user_id' => Field::text('Person', 'Their id.', '123456789012345678'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $thread = $this->id($config, 'channel_id', 'The thread');
        $user = $this->id($config, 'user_id', 'The person');

        $this->discord($run, $config, 'PUT', "/channels/{$thread}/thread-members/{$user}");

        return ['added' => true, 'thread_id' => $thread, 'user_id' => $user];
    }
}
