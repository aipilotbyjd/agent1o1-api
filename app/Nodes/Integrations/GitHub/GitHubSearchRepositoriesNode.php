<?php

namespace App\Nodes\Integrations\GitHub;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GitHubSearchRepositoriesNode extends AbstractGitHubNode
{
    public function type(): string
    {
        return 'github_search_repositories';
    }

    public function name(): string
    {
        return 'GitHub: Search Repositories';
    }

    public function description(): string
    {
        return 'Searches repositories with GitHub’s search syntax.';
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
                'query' => Field::text('Search', 'GitHub search syntax, e.g. language:php stars:>100 laravel.', 'language:php stars:>100 laravel'),
                'sort' => Field::select('Sort by', ['best-match' => 'Best match', 'stars' => 'Most stars', 'forks' => 'Most forks', 'updated' => 'Recently updated'], null, 'best-match'),
                'per_page' => Field::advanced(Field::integer('Results per page', default: 30, minimum: 1, maximum: 100)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = $this->get($run, '/search/repositories', $config, [
            'q' => $config['query'],
            'sort' => ($config['sort'] ?? 'best-match') === 'best-match' ? null : $config['sort'],
            'per_page' => $config['per_page'] ?? 30,
        ]);

        return ['total' => $result['total_count'] ?? 0, 'items' => $result['items'] ?? []];
    }
}
