<?php

namespace App\Services\Assistant\Briefings\Collectors;

use App\Models\Assistant\Assistant;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Assistant\Briefings\NodeReader;
use App\Services\Assistant\Briefings\SourceCollector;
use Carbon\CarbonInterface;

/**
 * Issues and pull requests updated since the last report, in the account's
 * most recently active repositories.
 */
class GitHubCollector implements SourceCollector
{
    private const int MAX_REPOS = 5;

    public function __construct(private readonly NodeReader $reader) {}

    public function source(): string
    {
        return 'github';
    }

    public function collect(Assistant $assistant, ConnectorCredential $credential, CarbonInterface $since, int $limit): array
    {
        $repos = collect($this->reader->read('github_list_repos', $assistant, $credential, ['per_page' => 50])['repos'] ?? [])
            ->sortByDesc(fn (array $repo): string => (string) ($repo['pushed_at'] ?? $repo['updated_at'] ?? ''))
            ->take(self::MAX_REPOS);

        return $repos
            ->flatMap(function (array $repo) use ($assistant, $credential, $since): array {
                $name = (string) $repo['full_name'];

                $issues = collect($this->reader->read('github_list_issues', $assistant, $credential, ['repo' => $name, 'state' => 'all', 'per_page' => 20])['issues'] ?? [])
                    ->reject(fn (array $issue): bool => isset($issue['pull_request']));
                $pulls = collect($this->reader->read('github_list_pull_requests', $assistant, $credential, ['repo' => $name, 'state' => 'all', 'per_page' => 20])['pull_requests'] ?? []);

                return $issues->map(fn (array $item): array => $this->item($name, 'Issue', $item))
                    ->concat($pulls->map(fn (array $item): array => $this->item($name, 'PR', $item)))
                    ->filter(fn (array $item): bool => $item['at'] !== null && now()->parse($item['at'])->gte($since))
                    ->all();
            })
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, detail: string, at: string|null, url: string|null, people: list<string>}
     */
    private function item(string $repo, string $kind, array $item): array
    {
        return [
            'title' => "{$repo} {$kind} #{$item['number']}: {$item['title']}",
            'detail' => trim(($item['state'] ?? '').'. '.mb_substr((string) ($item['body'] ?? ''), 0, 300)),
            'at' => $item['updated_at'] ?? null,
            'url' => $item['html_url'] ?? null,
            'people' => array_values(array_filter([(string) ($item['user']['login'] ?? '')])),
        ];
    }
}
