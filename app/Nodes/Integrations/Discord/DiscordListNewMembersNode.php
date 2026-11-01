<?php

namespace App\Nodes\Integrations\Discord;

use App\Contracts\SupportsTriggerMode;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * "New member". Discord pushes joins only over the gateway, so this reads the
 * server's member list (needs the "Server Members" intent) every five minutes
 * and starts a flow for each person it hasn't seen. It reads up to 5,000
 * members; a larger server is not fully covered. Someone who leaves and joins
 * again doesn't start it twice.
 */
class DiscordListNewMembersNode extends DiscordListMembersNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    /**
     * Members read in one pass, in pages of 1000.
     */
    private const int MAX_MEMBERS = 5000;

    public function type(): string
    {
        return 'discord_list_new_members';
    }

    public function name(): string
    {
        return 'Discord: List New Members';
    }

    public function description(): string
    {
        return 'Lists the members of a server by join date, oldest first. As a trigger it starts the flow for each person who joins (servers up to 5,000 members; needs the Server Members intent).';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['guild'],
            'properties' => [
                ...$this->credentialFields(),
                'guild' => $this->guildField(),
                'include_bots' => Field::boolean('Include bots', null, true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $guild = $this->id($config, 'guild', 'The server');
        $includeBots = filter_var($config['include_bots'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $members = [];
        $after = null;

        do {
            $page = array_values(array_filter((array) $this->discord($run, $config, 'GET', "/guilds/{$guild}/members", query: ['limit' => 1000, 'after' => $after]), 'is_array'));
            array_push($members, ...$page);
            $after = data_get(end($page) ?: [], 'user.id');
        } while (count($page) === 1000 && count($members) < self::MAX_MEMBERS);

        $members = array_values(array_filter($members, fn (array $member): bool => $includeBots || ! data_get($member, 'user.bot', false)));
        usort($members, fn (array $a, array $b): int => strcmp((string) ($a['joined_at'] ?? ''), (string) ($b['joined_at'] ?? '')));

        return ['members' => array_map(fn (array $member): array => $this->shapeMember($member), $members), 'count' => count($members)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'members';
    }

    /**
     * The person's id: a member who leaves and rejoins is the same one.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Discord: New member';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone joins a Discord server. Checks every five minutes.';
    }
}
