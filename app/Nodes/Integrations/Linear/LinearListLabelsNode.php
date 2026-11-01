<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListLabelsNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_labels';
    }

    public function name(): string
    {
        return 'Linear: List Labels';
    }

    public function description(): string
    {
        return 'Lists issue labels, for one team or all.';
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
                'team' => Field::dynamic('Team', 'linear.teams', 'Only this team\'s labels (and the workspace\'s). Leave empty for every label.', 'Engineering'),
                'limit' => Field::integer('Max results', null, 100, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teamId = $this->resolveId($run, $config, 'team', $config['team'] ?? null);

        $labels = $this->listAll(
            $run,
            $config,
            'query($filter: IssueLabelFilter, $first: Int, $after: String) { issueLabels(filter: $filter, first: $first, after: $after) { nodes { id name color description team { id key name } } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $teamId === null ? null : ['or' => [['team' => ['id' => ['eq' => $teamId]]], ['team' => ['null' => true]]]]],
            'issueLabels',
            $this->limit($config, 100),
        );

        return ['labels' => array_map(fn (array $label): array => ['id' => $label['id'] ?? null, 'name' => $label['name'] ?? null, 'color' => $label['color'] ?? null, 'description' => $label['description'] ?? null, 'team' => data_get($label, 'team.name'), 'team_id' => data_get($label, 'team.id')], $labels), 'count' => count($labels)];
    }
}
