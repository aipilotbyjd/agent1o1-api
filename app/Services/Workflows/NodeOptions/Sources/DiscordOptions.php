<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The servers the bot is in, their channels, and a server's roles. A bot sees
 * only its own servers, so a channel list reads each of them (up to a handful).
 * Discord can't search a list, so a search filters what was fetched.
 */
class DiscordOptions extends HttpOptionsSource
{
    private const string API_URL = 'https://discord.com/api/v10';

    /**
     * Most servers whose channels are read for one list.
     */
    private const int MAX_GUILDS = 10;

    public function sources(): array
    {
        return ['discord.guilds', 'discord.channels', 'discord.roles'];
    }

    protected function appName(): string
    {
        return 'Discord';
    }

    /**
     * The bot token goes in a `Bot` authorization header, not a bearer token.
     */
    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Bot '.$query->token, 'User-Agent' => 'DiscordBot ('.config('app.url').', 1.0)'])->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'discord.guilds' => $this->guilds($query),
            'discord.roles' => $this->roles($query),
            default => $this->channels($query),
        };
    }

    private function guilds(NodeOptionsQuery $query): NodeOptionsPage
    {
        return new NodeOptionsPage(
            collect($this->guildList($query))
                ->filter(fn (array $guild): bool => $query->matches((string) ($guild['name'] ?? '')))
                ->map(fn (array $guild): NodeOption => new NodeOption((string) $guild['id'], $guild['name'] ?? null, isset($guild['approximate_member_count']) ? $guild['approximate_member_count'].' members' : null)),
        );
    }

    /**
     * Every channel a message can be sent to, across the bot's servers (categories are skipped).
     */
    private function channels(NodeOptionsQuery $query): NodeOptionsPage
    {
        $options = [];

        foreach (array_slice($this->guildList($query), 0, self::MAX_GUILDS) as $guild) {
            $channels = collect($this->getJson($query, self::API_URL."/guilds/{$guild['id']}/channels"))
                ->filter(fn (mixed $channel): bool => is_array($channel) && isset($channel['id']) && ($channel['type'] ?? null) !== 4 && $query->matches((string) ($channel['name'] ?? ''), (string) ($guild['name'] ?? '')))
                ->sortBy(fn (array $channel): int => (int) ($channel['position'] ?? 0));

            foreach ($channels as $channel) {
                $options[] = new NodeOption((string) $channel['id'], '#'.($channel['name'] ?? $channel['id']), (string) ($guild['name'] ?? ''));
            }
        }

        return new NodeOptionsPage($options);
    }

    private function roles(NodeOptionsQuery $query): NodeOptionsPage
    {
        $guild = $query->configString('guild');

        if ($guild === null || preg_match('/^\d{15,25}$/', $guild) !== 1) {
            throw new ConnectorException('Choose a server first.');
        }

        return new NodeOptionsPage(
            collect($this->getJson($query, self::API_URL."/guilds/{$guild}/roles"))
                ->filter(fn (mixed $role): bool => is_array($role) && isset($role['id']) && ! ($role['managed'] ?? false) && $query->matches((string) ($role['name'] ?? '')))
                ->sortByDesc(fn (array $role): int => (int) ($role['position'] ?? 0))
                ->map(fn (array $role): NodeOption => new NodeOption((string) $role['id'], $role['name'] ?? null)),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function guildList(NodeOptionsQuery $query): array
    {
        return array_values(array_filter($this->getJson($query, self::API_URL.'/users/@me/guilds', ['limit' => 200, 'with_counts' => 'true']), 'is_array'));
    }
}
