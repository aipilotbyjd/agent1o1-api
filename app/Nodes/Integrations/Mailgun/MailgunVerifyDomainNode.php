<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunVerifyDomainNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_verify_domain';
    }

    public function name(): string
    {
        return 'Mailgun: Verify Domain';
    }

    public function description(): string
    {
        return 'Asks Mailgun to check the domain\'s DNS records again.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = (array) $this->mailgun($run, $config, 'PUT', '/v4/domains/'.$this->domain($run, $config).'/verify', []);

        return ['state' => data_get($found, 'domain.state'), 'verified' => data_get($found, 'domain.state') === 'active'];
    }
}
