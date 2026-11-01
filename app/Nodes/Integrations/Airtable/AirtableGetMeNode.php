<?php

namespace App\Nodes\Integrations\Airtable;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class AirtableGetMeNode extends AbstractAirtableNode
{
    public function type(): string
    {
        return 'airtable_get_me';
    }

    public function name(): string
    {
        return 'Airtable: Get Me';
    }

    public function description(): string
    {
        return 'Reads the connected Airtable user and the scopes of the token. Checks the connection works.';
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
        $me = $this->airtable($run, $config, 'GET', '/meta/whoami');

        return ['id' => $me['id'] ?? null, 'email' => $me['email'] ?? null, 'scopes' => array_values((array) ($me['scopes'] ?? []))];
    }
}
