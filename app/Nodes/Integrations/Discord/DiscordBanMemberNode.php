<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordBanMemberNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_ban_member';
    }

    public function name(): string
    {
        return 'Discord: Ban Member';
    }

    public function description(): string
    {
        return 'Bans a person from a server, optionally deleting what they posted in the last days.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'user_id' => Field::text('Person', 'Their id. They don\'t have to be in the server.', '123456789012345678'),
                'delete_days' => Field::integer('Delete their messages of the last (days)', '0 to 7.', 0, 0, 7),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $user = $this->id($config, 'user_id', 'The person');
        $days = (int) ($config['delete_days'] ?? 0);

        if ($days < 0 || $days > 7) {
            throw new InvalidArgumentException('Messages can be deleted for the last 0 to 7 days.');
        }

        $this->discord($run, $config, 'PUT', "/guilds/{$guild}/bans/{$user}", ['delete_message_seconds' => $days * 86400], reason: $config['reason'] ?? null);

        return ['banned' => true, 'guild' => $guild, 'user_id' => $user];
    }
}
