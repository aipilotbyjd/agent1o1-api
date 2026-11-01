<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class KlaviyoUpdateProfileNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_update_profile';
    }

    public function name(): string
    {
        return 'Klaviyo: Update Profile';
    }

    public function description(): string
    {
        return 'Changes a profile. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['profile'],
            'properties' => [
                ...$this->credentialFields(),
                'profile' => $this->profileField(),
                'email' => Field::text('New email'),
                ...$this->profileFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->profileId($run, $config);
        $attributes = $this->profileAttributes($config);

        if ($attributes === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapeProfile((array) data_get((array) $this->klaviyo($run, $config, 'PATCH', "/profiles/{$id}", ['data' => ['type' => 'profile', 'id' => $id, 'attributes' => $attributes]]), 'data', []));
    }
}
