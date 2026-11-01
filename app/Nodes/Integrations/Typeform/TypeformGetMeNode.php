<?php

namespace App\Nodes\Integrations\Typeform;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class TypeformGetMeNode extends AbstractTypeformNode
{
    public function type(): string
    {
        return 'typeform_get_me';
    }

    public function name(): string
    {
        return 'Typeform: Get Account';
    }

    public function description(): string
    {
        return 'Gets the account the token belongs to: its email, alias and language.';
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
        $me = (array) $this->typeform($run, $config, 'GET', '/me');

        return ['user_id' => $me['user_id'] ?? null, 'email' => $me['email'] ?? null, 'alias' => $me['alias'] ?? null, 'language' => $me['language'] ?? null];
    }
}
