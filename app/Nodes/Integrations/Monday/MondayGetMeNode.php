<?php

namespace App\Nodes\Integrations\Monday;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MondayGetMeNode extends AbstractMondayNode
{
    public function type(): string
    {
        return 'monday_get_me';
    }

    public function name(): string
    {
        return 'monday.com: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected monday.com user and account. Checks the connection works.';
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
        $me = (array) ($this->monday($run, $config, '{ me { id name email account { id name } } }')['me'] ?? []);

        return ['id' => isset($me['id']) ? (string) $me['id'] : null, 'name' => $me['name'] ?? null, 'email' => $me['email'] ?? null, 'account' => data_get($me, 'account.name')];
    }
}
