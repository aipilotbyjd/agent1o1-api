<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KlaviyoUpsertProfileNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_upsert_profile';
    }

    public function name(): string
    {
        return 'Klaviyo: Add or Update Profile';
    }

    public function description(): string
    {
        return 'Creates a profile, or updates the one with that email (or phone, or external id). Safe to run twice. Does not subscribe them to marketing: use Subscribe for that.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                ...$this->profileFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->email($config);
        $attributes = $this->profileAttributes($config);
        $result = (array) $this->klaviyo($run, $config, 'POST', '/profile-import/', ['data' => ['type' => 'profile', 'attributes' => $attributes]]);

        return $this->shapeProfile((array) ($result['data'] ?? []));
    }
}
