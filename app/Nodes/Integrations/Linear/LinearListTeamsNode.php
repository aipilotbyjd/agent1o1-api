<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListTeamsNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_teams';
    }

    public function name(): string
    {
        return 'Linear: List Teams';
    }

    public function description(): string
    {
        return 'Lists the teams of the workspace, with their keys.';
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
                'limit' => Field::integer('Max results', null, 50, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teams = $this->listAll($run, $config, 'query($first: Int, $after: String) { teams(first: $first, after: $after) { nodes { id key name description private } pageInfo { hasNextPage endCursor } } }', [], 'teams', $this->limit($config, 50));

        return ['teams' => array_map(fn (array $team): array => ['id' => $team['id'] ?? null, 'key' => $team['key'] ?? null, 'name' => $team['name'] ?? null, 'description' => $team['description'] ?? null, 'private' => $team['private'] ?? null], $teams), 'count' => count($teams)];
    }
}
