<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionListUsersNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_list_users';
    }

    public function name(): string
    {
        return 'Notion: List Users';
    }

    public function description(): string
    {
        return 'Lists the people and bots in the workspace.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                ...$this->credentialFields(),
                'include_bots' => Field::boolean('Include bots', default: false),
                'limit' => Field::integer('Limit', 'The most users to return (up to 500).', 100, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $users = array_values(array_filter(
            array_map($this->shapeUser(...), $this->list($run, 'get', '/users', $config, [], max(1, min(500, (int) ($config['limit'] ?? 100))))),
            fn (array $user): bool => ($config['include_bots'] ?? false) || $user['type'] === 'person',
        ));

        return ['users' => $users, 'count' => count($users)];
    }
}
