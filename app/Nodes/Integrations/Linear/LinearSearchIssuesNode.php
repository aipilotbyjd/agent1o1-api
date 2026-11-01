<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearSearchIssuesNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_search_issues';
    }

    public function name(): string
    {
        return 'Linear: Search Issues';
    }

    public function description(): string
    {
        return 'Searches issue titles, descriptions and comments for some text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['term'],
            'properties' => [
                ...$this->credentialFields(),
                'term' => Field::text('Search for', 'Words to look for in issues.', 'login bug'),
                'team' => Field::dynamic('Team', 'linear.teams', 'Only issues of this team. Leave empty for every team.', 'Engineering'),
                'limit' => Field::integer('Max results', null, 10, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $term = $this->requiredString($config, 'term', 'The search text');
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);

        $issues = $this->listAll(
            $run,
            $config,
            'query($term: String!, $filter: IssueFilter, $first: Int, $after: String) { searchIssues(term: $term, filter: $filter, first: $first, after: $after) { nodes { '.self::ISSUE_FIELDS.' } pageInfo { hasNextPage endCursor } } }',
            ['term' => $term, 'filter' => $teamId === null ? null : ['team' => ['id' => ['eq' => $teamId]]]],
            'searchIssues',
            $this->limit($config, 10),
        );

        return ['issues' => array_map($this->shapeIssue(...), $issues), 'count' => count($issues)];
    }
}
