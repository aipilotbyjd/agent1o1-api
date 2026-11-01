<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ResendGetEmailNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_get_email';
    }

    public function name(): string
    {
        return 'Resend: Get Email';
    }

    public function description(): string
    {
        return 'Reads a sent email: its recipients, subject and latest status (delivered, bounced, opened...).';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_id'],
            'properties' => [
                ...$this->credentialFields(),
                'email_id' => Field::text('Email id', 'The id Send Email returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeEmail((array) $this->resend($run, $config, 'GET', '/emails/'.$this->pathPart($config, 'email_id', 'The email id')));
    }
}
