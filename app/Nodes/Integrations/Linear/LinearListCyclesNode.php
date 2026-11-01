<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListCyclesNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_cycles';
    }

    public function name(): string
    {
        return 'Linear: List Cycles';
    }

    public function description(): string
    {
        return 'Lists the cycles (sprints) of a team, newest first.';
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
                'team' => Field::dynamic('Team', 'linear.teams', 'Only this team\'s cycles. Leave empty for every team.', 'Engineering'),
                'limit' => Field::integer('Max results', null, 25, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);

        $cycles = $this->listAll(
            $run,
            $config,
            'query($filter: CycleFilter, $first: Int, $after: String) { cycles(filter: $filter, first: $first, after: $after, orderBy: createdAt) { nodes { id number name startsAt endsAt completedAt team { id key name } } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $teamId === null ? null : ['team' => ['id' => ['eq' => $teamId]]]],
            'cycles',
            $this->limit($config, 25),
        );

        return ['cycles' => array_map(fn (array $cycle): array => ['id' => $cycle['id'] ?? null, 'number' => $cycle['number'] ?? null, 'name' => $cycle['name'] ?? null, 'starts_at' => $cycle['startsAt'] ?? null, 'ends_at' => $cycle['endsAt'] ?? null, 'completed_at' => $cycle['completedAt'] ?? null, 'team' => data_get($cycle, 'team.name'), 'team_key' => data_get($cycle, 'team.key')], $cycles), 'count' => count($cycles)];
    }
}
