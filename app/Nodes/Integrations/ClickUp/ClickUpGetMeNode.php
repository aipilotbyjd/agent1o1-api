<?php

namespace App\Nodes\Integrations\ClickUp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ClickUpGetMeNode extends AbstractClickUpNode
{
    public function type(): string
    {
        return 'clickup_get_me';
    }

    public function name(): string
    {
        return 'ClickUp: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected ClickUp user. Checks the connection works.';
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
        $user = (array) ($this->clickup($run, $config, 'GET', '/user')['user'] ?? []);

        return ['id' => $user['id'] ?? null, 'name' => $user['username'] ?? null, 'email' => $user['email'] ?? null];
    }
}
