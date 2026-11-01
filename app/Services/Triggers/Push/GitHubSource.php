<?php

namespace App\Services\Triggers\Push;

use App\Models\Triggers\ProviderWatch;
use App\Models\Triggers\Trigger;

/**
 * Issues, pull requests, commits, releases, stars, forks, branches, comments
 * and workflow runs in a GitHub repository through a repository webhook,
 * shared by every trigger on that repository and event.
 *
 * Needs admin rights on the repository; without them GitHub refuses (403/404)
 * and the trigger keeps checking on a timer. The hook does not expire. The
 * trigger's own poll fetches the items.
 */
class GitHubSource extends AbstractWatchedSource
{
    private const string API = 'https://api.github.com';

    /**
     * The hook event each reader node is about.
     *
     * @var array<string, string>
     */
    private const array EVENTS = [
        'github_list_issues' => 'issues',
        'github_list_closed_issues' => 'issues',
        'github_list_pull_requests' => 'pull_request',
        'github_list_merged_pull_requests' => 'pull_request',
        'github_list_commits' => 'push',
        'github_list_releases' => 'release',
        // GitHub names a star event "watch".
        'github_list_stargazers' => 'watch',
        'github_list_forks' => 'fork',
        'github_list_branches' => 'create',
        'github_list_repo_comments' => 'issue_comment',
        'github_list_workflow_runs' => 'workflow_run',
    ];

    public function provider(): string
    {
        return 'github';
    }

    protected function nodeTypes(): array
    {
        return array_keys(self::EVENTS);
    }

    public function supports(Trigger $trigger): bool
    {
        return parent::supports($trigger) && filled($this->nodeConfig($trigger)['repo'] ?? null);
    }

    public function watchKeyFor(Trigger $trigger): ?string
    {
        $event = self::EVENTS[$trigger->config['node_type'] ?? ''] ?? null;
        $repo = $this->nodeConfig($trigger)['repo'] ?? null;

        return $event !== null && is_string($repo) ? strtolower($repo).':'.$event : null;
    }

    public function createWatch(ProviderWatch $watch, Trigger $trigger): void
    {
        [$repo, $event] = explode(':', $watch->resource_key, 2);
        $secret = $this->newSecret();

        $body = $this->ok($this->http($this->credentialOf($watch))
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post(self::API."/repos/{$repo}/hooks", [
                'name' => 'web',
                'active' => true,
                'events' => [$event],
                'config' => ['url' => $this->callbackUrl(), 'content_type' => 'json', 'secret' => $secret, 'insecure_ssl' => '0'],
            ]))->json();

        $watch->external_id = (string) $body['id'];
        $watch->secret = $secret;
        $watch->meta = [...(array) $watch->meta, 'repo' => $repo, 'event' => $event];
        $watch->expires_at = null;
        $watch->renew_at = null;
    }

    /**
     * A GitHub hook does not expire.
     */
    public function renewWatch(ProviderWatch $watch): void {}

    public function deleteWatch(ProviderWatch $watch): void
    {
        $repo = $watch->meta['repo'] ?? null;

        if ($watch->external_id !== null && is_string($repo)) {
            $this->http($this->credentialOf($watch))->delete(self::API."/repos/{$repo}/hooks/{$watch->external_id}");
        }
    }

    /**
     * The repository and hook id, because hook ids are only unique per repository.
     */
    public function routingKeyFor(Trigger $trigger, ?ProviderWatch $watch = null): ?string
    {
        $repo = $watch?->meta['repo'] ?? null;

        return is_string($repo) && $watch?->external_id !== null ? $repo.'#'.$watch->external_id : null;
    }
}
