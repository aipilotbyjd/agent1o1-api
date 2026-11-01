<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * Slack channels and members. Slack's list APIs can't search, so a search
 * filters the fetched page.
 */
class SlackOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://slack.com/api/';

    private const int PAGE_SIZE = 200;

    public function sources(): array
    {
        return ['slack.channels', 'slack.users'];
    }

    protected function appName(): string
    {
        return 'Slack';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $source === 'slack.users' ? $this->users($query) : $this->channels($query);
    }

    private function channels(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->slack($query, 'conversations.list', [
            'types' => 'public_channel,private_channel',
            'exclude_archived' => 'true',
            'limit' => self::PAGE_SIZE,
            'cursor' => $query->cursor,
        ]);

        $options = collect($body['channels'] ?? [])
            ->filter(fn (array $channel): bool => isset($channel['id']) && $query->matches((string) ($channel['name'] ?? '')))
            ->map(fn (array $channel): NodeOption => new NodeOption(
                (string) $channel['id'],
                '#'.($channel['name'] ?? $channel['id']),
                ($channel['is_private'] ?? false) ? 'Private channel' : null,
            ));

        return new NodeOptionsPage($options, $body['response_metadata']['next_cursor'] ?? null);
    }

    private function users(NodeOptionsQuery $query): NodeOptionsPage
    {
        $body = $this->slack($query, 'users.list', ['limit' => self::PAGE_SIZE, 'cursor' => $query->cursor]);

        $options = collect($body['members'] ?? [])
            ->filter(fn (array $member): bool => isset($member['id'])
                && ! ($member['deleted'] ?? false)
                && ! ($member['is_bot'] ?? false)
                && $member['id'] !== 'USLACKBOT')
            ->filter(fn (array $member): bool => $query->matches(
                (string) ($member['real_name'] ?? ''),
                (string) ($member['name'] ?? ''),
                (string) ($member['profile']['email'] ?? ''),
            ))
            ->map(fn (array $member): NodeOption => new NodeOption(
                (string) $member['id'],
                $member['real_name'] ?? $member['name'] ?? null,
                isset($member['name']) ? '@'.$member['name'] : null,
            ));

        return new NodeOptionsPage($options, $body['response_metadata']['next_cursor'] ?? null);
    }

    /**
     * Slack answers 200 even on failure, with `ok: false` + an error code.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ConnectorException
     */
    private function slack(NodeOptionsQuery $query, string $method, array $params): array
    {
        $body = $this->getJson($query, self::BASE_URL.$method, $params);

        if (($body['ok'] ?? false) === true) {
            return $body;
        }

        $error = (string) ($body['error'] ?? 'unknown_error');
        $this->logFailure(['method' => $method, 'error' => $error]);

        throw new ConnectorException(match ($error) {
            'invalid_auth', 'not_authed', 'token_revoked', 'token_expired', 'account_inactive' => 'Slack rejected the connected account. Reconnect it in Apps.',
            'missing_scope' => 'The connected Slack account is missing a permission this list needs. Reconnect it in Apps.',
            'ratelimited' => 'Slack is rate limiting requests. Try again in a moment.',
            default => 'Couldn\'t load options from Slack.',
        });
    }
}
