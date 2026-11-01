<?php

namespace App\Nodes\Integrations\Discord;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DiscordTimeoutMemberNode extends AbstractDiscordNode
{
    public function type(): string
    {
        return 'discord_timeout_member';
    }

    public function name(): string
    {
        return 'Discord: Time Out Member';
    }

    public function description(): string
    {
        return 'Stops a person writing or speaking for a while (up to 28 days), or ends a timeout.';
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
                'user_id' => Field::text('Person', 'Their id.', '123456789012345678'),
                'minutes' => Field::integer('Time out for (minutes)', 'Between 1 and 40320 (28 days). 0 ends a timeout.', 60, 0, 40320),
                'reason' => Field::advanced(Field::text('Reason', 'Shown in the server\'s audit log.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $user = $this->id($config, 'user_id', 'The person');
        $minutes = (int) ($config['minutes'] ?? 60);

        if ($minutes < 0 || $minutes > 40320) {
            throw new InvalidArgumentException('A timeout is between 1 minute and 28 days (40320 minutes). Use 0 to end one.');
        }

        $member = (array) $this->discord($run, $config, 'PATCH', "/guilds/{$guild}/members/{$user}", ['communication_disabled_until' => $minutes > 0 ? now()->addMinutes($minutes)->toIso8601String() : null], reason: $config['reason'] ?? null, keepNulls: true);

        return $this->shapeMember($member);
    }
}
