<?php

namespace App\Nodes\Integrations\ZohoDesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoDeskListDepartmentsNode extends AbstractZohoDeskNode
{
    public function type(): string
    {
        return 'zoho_desk_list_departments';
    }

    public function name(): string
    {
        return 'Zoho Desk: List Departments';
    }

    public function description(): string
    {
        return 'Lists the departments, whose ids a new ticket needs.';
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
        $departments = $this->listAll($run, $config, '/departments', [], 100);

        return ['departments' => array_map(fn (array $department): array => ['id' => isset($department['id']) ? (string) $department['id'] : null, 'name' => $department['name'] ?? null, 'enabled' => (bool) ($department['isEnabled'] ?? true)], $departments), 'count' => count($departments)];
    }
}
