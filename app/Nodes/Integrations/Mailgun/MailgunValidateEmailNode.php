<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunValidateEmailNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_validate_email';
    }

    public function name(): string
    {
        return 'Mailgun: Validate Email';
    }

    public function description(): string
    {
        return 'Checks whether an address can receive mail: deliverable, risky or not. Needs Mailgun\'s validation service on the plan.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $result = (array) $this->mailgun($run, $config, 'GET', '/v4/address/validate', query: ['address' => $this->email($config)]);

        return ['email' => $result['address'] ?? $this->email($config), 'result' => $result['result'] ?? null, 'risk' => $result['risk'] ?? null, 'reason' => array_values((array) ($result['reason'] ?? [])), 'disposable' => (bool) ($result['is_disposable_address'] ?? false), 'role' => (bool) ($result['is_role_address'] ?? false), 'did_you_mean' => $result['did_you_mean'] ?? null];
    }
}
