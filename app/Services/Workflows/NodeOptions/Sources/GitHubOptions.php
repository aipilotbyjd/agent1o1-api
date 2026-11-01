<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Validation\ValidationException;

/**
 * GitHub organisations, repositories, and a repository's branches, issues,
 * labels and assignees. Pages are GitHub's own page numbers; a search
 * filters the fetched page.
 */
class GitHubOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://api.github.com';

    private const int PER_PAGE = 100;

    /**
     * GitHub's own rules for an owner (`acme-inc`) and a repo name
     * (`widgets.js`), never `.`/`..`, which would walk the request path.
     */
    private const string REPO_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9-]{0,38}\/(?!\.+$)[A-Za-z0-9._-]{1,100}$/';

    public function sources(): array
    {
        return ['github.owners', 'github.repos', 'github.branches', 'github.issues', 'github.labels', 'github.assignees'];
    }

    protected function appName(): string
    {
        return 'GitHub';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return parent::http($query)->withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ]);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'github.owners' => $this->owners($query),
            'github.repos' => $this->list($query, '/user/repos', ['sort' => 'updated', 'affiliation' => 'owner,collaborator,organization_member'], 'full_name', fn (array $repo): NodeOption => new NodeOption(
                (string) $repo['full_name'],
                (string) $repo['full_name'],
                ($repo['private'] ?? false) ? 'Private' : 'Public',
            )),
            'github.branches' => $this->list($query, $this->repoPath($query).'/branches', [], 'name', fn (array $branch): NodeOption => new NodeOption(
                (string) $branch['name'],
            )),
            'github.issues' => $this->list($query, $this->repoPath($query).'/issues', ['state' => 'all', 'sort' => 'updated'], 'number', fn (array $issue): NodeOption => new NodeOption(
                (int) $issue['number'],
                "#{$issue['number']} ".($issue['title'] ?? ''),
                (isset($issue['pull_request']) ? 'Pull request' : 'Issue').' · '.($issue['state'] ?? 'open'),
            )),
            'github.labels' => $this->list($query, $this->repoPath($query).'/labels', [], 'name', fn (array $label): NodeOption => new NodeOption(
                (string) $label['name'],
                (string) $label['name'],
                $label['description'] ?? null,
            )),
            default => $this->list($query, $this->repoPath($query).'/assignees', [], 'login', fn (array $user): NodeOption => new NodeOption(
                (string) $user['login'],
            )),
        };
    }

    /**
     * The organisations the signed-in user belongs to: leaving the field
     * empty means their own account.
     */
    private function owners(NodeOptionsQuery $query): NodeOptionsPage
    {
        $orgs = $this->getJson($query, self::BASE_URL.'/user/orgs', ['per_page' => self::PER_PAGE]);

        $options = collect($orgs)
            ->filter(fn (mixed $org): bool => is_array($org) && isset($org['login']) && $query->matches((string) $org['login']))
            ->map(fn (array $org): NodeOption => new NodeOption((string) $org['login'], (string) $org['login'], $org['description'] ?? null));

        return new NodeOptionsPage($options);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  string  $valueKey  items without it are skipped
     * @param  callable(array<string, mixed>): NodeOption  $toOption
     */
    private function list(NodeOptionsQuery $query, string $path, array $params, string $valueKey, callable $toOption): NodeOptionsPage
    {
        $page = $this->intCursor($query);
        $items = $this->getJson($query, self::BASE_URL.$path, [...$params, 'per_page' => self::PER_PAGE, 'page' => $page]);

        $options = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item[$valueKey]))
            ->map(fn (array $item): NodeOption => $toOption($item))
            ->filter(fn (NodeOption $option): bool => $query->matches($option->label));

        return new NodeOptionsPage($options, count($items) === self::PER_PAGE ? $page + 1 : null);
    }

    /**
     * `/repos/{owner}/{name}` for the node's `repo` field, which must be a
     * real `owner/name`: anything else could point the request elsewhere.
     *
     * @throws ValidationException
     */
    private function repoPath(NodeOptionsQuery $query): string
    {
        $repo = trim((string) $query->configString('repo'));

        if (preg_match(self::REPO_PATTERN, $repo) !== 1) {
            throw ValidationException::withMessages(['config.repo' => 'Repository must look like owner/name.']);
        }

        return "/repos/{$repo}";
    }
}
