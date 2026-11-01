<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DiscordArchiveThreadNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_archive_thread';
    }

    public function name(): string
    {
        return 'Discord: Archive or Lock Thread';
    }

    public function description(): string
    {
        return 'Archives or reopens a thread, and locks or unlocks it so only moderators can write.';
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
                'channel_id' => $this->channelField('Thread', 'The thread\'s id, or pick it.'),
                'archive' => Field::select('Archive', ['unchanged' => 'Leave as it is', 'archive' => 'Archive', 'reopen' => 'Reopen'], null, 'archive'),
                'lock' => Field::select('Lock', ['unchanged' => 'Leave as it is', 'lock' => 'Lock', 'unlock' => 'Unlock'], null, 'unchanged'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $thread = $this->id($config, 'channel_id', 'The thread');
        $archive = (string) ($config['archive'] ?? 'archive');
        $lock = (string) ($config['lock'] ?? 'unchanged');

        $updated = (array) $this->discord($run, $config, 'PATCH', "/channels/{$thread}", [
            'archived' => $archive === 'archive' ? true : ($archive === 'reopen' ? false : null),
            'locked' => $lock === 'lock' ? true : ($lock === 'unlock' ? false : null),
        ]);

        return $this->shapeChannel($updated);
    }
}
