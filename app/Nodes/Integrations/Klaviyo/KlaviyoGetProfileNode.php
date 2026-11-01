<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoGetProfileNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_get_profile';
    }

    public function name(): string
    {
        return 'Klaviyo: Get Profile';
    }

    public function description(): string
    {
        return 'Gets a profile by email or id, with their properties and marketing consent.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['profile'],
            'properties' => [
                ...$this->credentialFields(),
                'profile' => $this->profileField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->profileId($run, $config);

        return $this->shapeProfile((array) data_get((array) $this->klaviyo($run, $config, 'GET', "/profiles/{$id}", query: ['additional-fields[profile]' => 'subscriptions']), 'data', []));
    }
}
