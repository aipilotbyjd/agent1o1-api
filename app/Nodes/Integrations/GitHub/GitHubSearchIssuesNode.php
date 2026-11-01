<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubSearchIssuesNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_search_issues';
    }

    public function name(): string
    {
        return 'GitHub: Search Issues and Pull Requests';
    }

    public function description(): string
    {
        return 'Searches issues and pull requests with GitHub’s search syntax.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search', 'GitHub search syntax, e.g. repo:acme/widgets is:open label:bug.', 'repo:acme/widgets is:open label:bug'),
                'sort' => Field::select('Sort by', ['best-match' => 'Best match', 'created' => 'Newest', 'updated' => 'Recently updated', 'comments' => 'Most comments'], null, 'best-match'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->get($run, '/search/issues', $config, [
            'q' => $config['query'],
            'sort' => ($config['sort'] ?? 'best-match') === 'best-match' ? null : $config['sort'],
            'per_page' => $config['per_page'] ?? 30,
        ]);

        return ['total' => $result['total_count'] ?? 0, 'items' => $result['items'] ?? []];
    }
}
