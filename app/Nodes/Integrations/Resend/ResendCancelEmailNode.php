<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ResendCancelEmailNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_cancel_email';
    }

    public function name(): string
    {
        return 'Resend: Cancel Scheduled Email';
    }

    public function description(): string
    {
        return 'Cancels an email that is scheduled and has not been sent yet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email_id'],
            'properties' => [
                ...$this->credentialFields(),
                'email_id' => Field::text('Email id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathPart($config, 'email_id', 'The email id');

        $this->resend($run, $config, 'POST', "/emails/{$id}/cancel");

        return ['canceled' => true, 'id' => trim((string) $config['email_id'])];
    }
}
