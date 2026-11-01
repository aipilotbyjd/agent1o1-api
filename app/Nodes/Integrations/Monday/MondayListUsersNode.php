<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MondayListUsersNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_list_users';
    }

    public function name(): string
    {
        return 'monday.com: List Users';
    }

    public function description(): string
    {
        return 'Lists the people in the account, to assign items to.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $users = array_values(array_filter((array) ($this->monday($run, $config, 'query ($limit: Int) { users(limit: $limit, kind: non_guests) { id name email title } }', ['limit' => $this->limit($config, 100, 500)])['users'] ?? []), 'is_array'));

        return ['users' => array_map(fn (array $user): array => ['id' => isset($user['id']) ? (string) $user['id'] : null, 'name' => $user['name'] ?? null, 'email' => $user['email'] ?? null, 'title' => $user['title'] ?? null], $users), 'count' => count($users)];
    }
}
