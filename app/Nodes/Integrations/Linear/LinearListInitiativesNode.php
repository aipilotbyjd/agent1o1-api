<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListInitiativesNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_initiatives';
    }

    public function name(): string
    {
        return 'Linear: List Initiatives';
    }

    public function description(): string
    {
        return 'Lists initiatives, optionally by name or status, with the projects each holds.';
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
                'query' => Field::text('Name contains', 'Only initiatives whose name has this text.'),
                'status' => Field::select('Status', ['Planned' => 'Planned', 'Active' => 'Active', 'Completed' => 'Completed'], 'Only initiatives with this status.'),
                'limit' => Field::integer('Max results', null, 25, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filter = filled($config['query'] ?? null) ? ['name' => ['containsIgnoreCase' => (string) $config['query']]] : null;

        $initiatives = $this->listAll(
            $run,
            $config,
            'query($filter: InitiativeFilter, $first: Int, $after: String) { initiatives(filter: $filter, first: $first, after: $after) { nodes { '.self::INITIATIVE_FIELDS.' } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $filter],
            'initiatives',
            $this->limit($config, 25),
        );

        $initiatives = array_map($this->shapeInitiative(...), $initiatives);

        if (filled($config['status'] ?? null)) {
            $initiatives = array_values(array_filter($initiatives, fn (array $initiative): bool => strcasecmp((string) $initiative['status'], (string) $config['status']) === 0));
        }

        return ['initiatives' => $initiatives, 'count' => count($initiatives)];
    }
}
