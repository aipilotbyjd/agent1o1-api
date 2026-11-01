<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListProjectsNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_projects';
    }

    public function name(): string
    {
        return 'Linear: List Projects';
    }

    public function description(): string
    {
        return 'Lists projects, optionally of one team, with a name or a status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'team' => Field::dynamic('Team', 'linear.teams', 'Only projects this team works on. Leave empty for every team.', 'Engineering'),
                'query' => Field::text('Name contains', 'Only projects whose name has this text.'),
                'status' => Field::text('Status', 'Only projects with this status, e.g. Planned, Started or Completed.', 'Started'),
                'limit' => Field::integer('Max results', null, 25, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);

        $filter = array_filter([
            'accessibleTeams' => $teamId === null ? null : ['some' => ['id' => ['eq' => $teamId]]],
            'name' => filled($config['query'] ?? null) ? ['containsIgnoreCase' => (string) $config['query']] : null,
        ], fn (mixed $value): bool => $value !== null);

        $projects = $this->listAll(
            $run,
            $config,
            'query($filter: ProjectFilter, $first: Int, $after: String) { projects(filter: $filter, first: $first, after: $after) { nodes { '.self::PROJECT_FIELDS.' } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $filter ?: null],
            'projects',
            $this->limit($config, 25),
        );

        $projects = array_map($this->shapeProject(...), $projects);

        if (filled($config['status'] ?? null)) {
            $status = strtolower(trim((string) $config['status']));
            $projects = array_values(array_filter($projects, fn (array $project): bool => strtolower((string) $project['status']) === $status));
        }

        return ['projects' => $projects, 'count' => count($projects)];
    }
}
