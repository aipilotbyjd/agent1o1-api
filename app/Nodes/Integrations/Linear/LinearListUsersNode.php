<?php

namespace App\Nodes\Integrations\Linear;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinearListUsersNode extends AbstractLinearNode
{
    public function type(): string
    {
        return 'linear_list_users';
    }

    public function name(): string
    {
        return 'Linear: List Users';
    }

    public function description(): string
    {
        return 'Lists the people of the workspace, with their emails.';
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
                'query' => Field::text('Name or email contains', 'Only people matching this text.'),
                'include_inactive' => Field::boolean('Include people who left'),
                'limit' => Field::integer('Max results', null, 50, 1, 250),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $filter = array_filter([
            'active' => ($config['include_inactive'] ?? false) === true ? null : ['eq' => true],
            'or' => filled($config['query'] ?? null) ? [['name' => ['containsIgnoreCase' => (string) $config['query']]], ['email' => ['containsIgnoreCase' => (string) $config['query']]]] : null,
        ], fn (mixed $value): bool => $value !== null);

        $users = $this->listAll(
            $run,
            $config,
            'query($filter: UserFilter, $first: Int, $after: String) { users(filter: $filter, first: $first, after: $after) { nodes { id name displayName email active admin url } pageInfo { hasNextPage endCursor } } }',
            ['filter' => $filter ?: null],
            'users',
            $this->limit($config, 50),
        );

        return ['users' => array_map($this->shapeUser(...), $users), 'count' => count($users)];
    }
}
