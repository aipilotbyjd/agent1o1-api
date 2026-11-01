<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ResendCreateAudienceNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_create_audience';
    }

    public function name(): string
    {
        return 'Resend: Create Audience';
    }

    public function description(): string
    {
        return 'Creates an audience.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Newsletter'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $audience = (array) $this->resend($run, $config, 'POST', '/audiences', ['name' => $this->requiredString($config, 'name', 'The name')]);

        return ['id' => $audience['id'] ?? null, 'name' => $audience['name'] ?? null];
    }
}
