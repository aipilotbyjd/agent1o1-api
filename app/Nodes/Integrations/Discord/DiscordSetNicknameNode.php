<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordSetNicknameNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_set_nickname';
    }

    public function name(): string
    {
        return 'Discord: Set Nickname';
    }

    public function description(): string
    {
        return 'Changes a person\'s nickname in a server, or resets it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild', 'user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'user_id' => Field::text('Person', 'Their id. Use the bot\'s own id to rename the bot.', '123456789012345678'),
                'nickname' => Field::text('Nickname', 'Up to 32 characters. Leave empty to reset it.'),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $user = $this->id($config, 'user_id', 'The person');
        $nickname = trim((string) ($config['nickname'] ?? ''));

        if (mb_strlen($nickname) > 32) {
            throw new InvalidArgumentException('A nickname is at most 32 characters.');
        }

        $member = (array) $this->discord($run, $config, 'PATCH', "/guilds/{$guild}/members/{$user}", ['nick' => $nickname !== '' ? $nickname : null], reason: $config['reason'] ?? null, keepNulls: true);

        return $this->shapeMember($member);
    }
}
