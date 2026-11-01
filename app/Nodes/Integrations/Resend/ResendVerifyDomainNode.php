<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ResendVerifyDomainNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_verify_domain';
    }

    public function name(): string
    {
        return 'Resend: Verify Domain';
    }

    public function description(): string
    {
        return 'Asks Resend to check a domain\'s DNS records again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain_id'],
            'properties' => [
                ...$this->credentialFields(),
                'domain_id' => Field::dynamic('Domain', 'resend.domains', null, 'example.com'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->resend($run, $config, 'POST', '/domains/'.$this->pathPart($config, 'domain_id', 'The domain').'/verify');

        return ['verification_started' => true, 'domain_id' => trim((string) $config['domain_id'])];
    }
}
