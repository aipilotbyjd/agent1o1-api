<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListWorkflowStatesNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_workflow_states';
    }

    public function name(): string
    {
        return 'Linear: List Statuses';
    }

    public function description(): string
    {
        return 'Lists the statuses issues can be in (Backlog, In Progress, Done...), for one team or all.';
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
                'team' => Field::dynamic('Team', 'linear.teams', 'Only this team\'s statuses. Leave empty for every team.', 'Engineering'),
                'limit' => Field::integer('Max results', null, 100, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);

        $states = $this->listAll(
            $run,
            $config,
            'query($filter: WorkflowStateFilter, $first: Int, $after: String) { workflowStates(filter: $filter, first: $first, after: $after) { nodes { id name type color position team { id key name } } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $teamId === null ? null : ['team' => ['id' => ['eq' => $teamId]]]],
            'workflowStates',
            $this->limit($config, 100),
        );

        return ['states' => array_map(fn (array $state): array => ['id' => $state['id'] ?? null, 'name' => $state['name'] ?? null, 'type' => $state['type'] ?? null, 'color' => $state['color'] ?? null, 'position' => $state['position'] ?? null, 'team' => data_get($state, 'team.name'), 'team_key' => data_get($state, 'team.key'), 'team_id' => data_get($state, 'team.id')], $states), 'count' => count($states)];
    }
}
