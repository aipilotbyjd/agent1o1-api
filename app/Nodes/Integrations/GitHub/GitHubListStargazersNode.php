<?php

namespace App\Nodes\Integrations\GitHub;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The people who starred a repository, newest last.
 *
 * GitHub lists stargazers oldest first, so the newest are on the last page.
 * Reading from the first page would show a popular repository's first stars
 * forever; this reads the last pages instead, and keeps the people with their
 * star time.
 */
class GitHubListStargazersNode extends AbstractGitHubNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int PER_PAGE = 100;

    public function type(): string
    {
        return 'github_list_stargazers';
    }

    public function name(): string
    {
        return 'GitHub: List Stargazers';
    }

    public function description(): string
    {
        return 'Lists the people who starred a repository, with when they did, newest last.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['repo'],
            'properties' => [
                ...$this->credentialFields(),
                'repo' => Field::dynamic('Repository', 'github.repos', 'In owner/name form.', 'acme/widgets'),
                'limit' => Field::integer('Newest stars to return', 'How many of the latest stars to read (up to 200).', 100, 1, 200),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $repo = $this->repo($config);
        $endpoint = "/repos/{$repo}/stargazers";
        $last = $this->lastPage($run, $endpoint, $config, self::PER_PAGE);
        $token = $this->resolveAccessToken($run, $config);

        $stargazers = [];

        // The last page may hold only a few, so the one before it fills the gap.
        foreach ($last > 1 ? [$last - 1, $last] : [1] as $page) {
            // The star+json type adds the time of each star, which the plain list leaves out.
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github.star+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->get("https://api.github.com{$endpoint}", ['per_page' => self::PER_PAGE, 'page' => $page]);

            if ($response->failed()) {
                throw new RuntimeException("GitHub API error [{$endpoint}]: ".($response->json('message') ?? $response->body()));
            }

            foreach ((array) $response->json() as $entry) {
                if (is_array($entry) && isset($entry['user']['login'])) {
                    $stargazers[] = [
                        'login' => $entry['user']['login'],
                        'id' => $entry['user']['id'] ?? null,
                        'url' => $entry['user']['html_url'] ?? null,
                        'avatar_url' => $entry['user']['avatar_url'] ?? null,
                        'starred_at' => $entry['starred_at'] ?? null,
                    ];
                }
            }
        }

        return ['stargazers' => array_slice($stargazers, -max(1, (int) ($config['limit'] ?? self::PER_PAGE)))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'stargazers';
    }

    /**
     * Only the last stars: one poll looks at the first `poll_max_items` items of
     * what it is given, and the newest are at the end of this list.
     */
    public function triggerItems(array $output, array $config): array
    {
        $stargazers = array_values(array_filter((array) ($output['stargazers'] ?? []), 'is_array'));

        return array_slice($stargazers, -max(1, (int) config('triggers.poll_max_items')));
    }

    /**
     * A person is named by their login: starring twice does not run twice.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['login'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'GitHub: New star';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when someone stars the repository.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
