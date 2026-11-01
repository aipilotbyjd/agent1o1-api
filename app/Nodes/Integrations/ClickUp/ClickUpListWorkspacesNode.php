<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ClickUpListWorkspacesNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_list_workspaces';
    }

    public function name(): string
    {
        return 'ClickUp: List Workspaces';
    }

    public function description(): string
    {
        return 'Lists the workspaces the user belongs to, with their members.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $teams = array_values(array_filter((array) ($this->clickup($run, $config, 'GET', '/team')['teams'] ?? []), 'is_array'));

        return ['workspaces' => array_map(fn (array $team): array => ['id' => $team['id'] ?? null, 'name' => $team['name'] ?? null, 'members' => array_values(array_filter(array_map(fn (mixed $member): ?array => is_array($member) && isset($member['user']) ? ['id' => $member['user']['id'] ?? null, 'name' => $member['user']['username'] ?? null, 'email' => $member['user']['email'] ?? null] : null, (array) ($team['members'] ?? []))))], $teams), 'count' => count($teams)];
    }
}
