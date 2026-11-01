<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailgunRemoveSuppressionNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_remove_suppression';
    }

    public function name(): string
    {
        return 'Mailgun: Remove Suppression';
    }

    public function description(): string
    {
        return 'Lets Mailgun send to an address again by taking it off a suppression list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'kind'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'kind' => Field::select('From', ['bounces' => 'Bounces', 'unsubscribes' => 'Unsubscribes', 'complaints' => 'Spam complaints'], null, 'bounces'),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $kind = (string) ($config['kind'] ?? 'bounces');
        $email = $this->email($config);

        if (! in_array($kind, ['bounces', 'unsubscribes', 'complaints'], true)) {
            throw new InvalidArgumentException("\"{$kind}\" is not a suppression list.");
        }

        $response = $this->mailgunResponse($run, $config, 'DELETE', "/v3/{$domain}/{$kind}/".rawurlencode($email));

        if ($response->status() === 404) {
            return ['removed' => false, 'found' => false, 'email' => $email, 'kind' => $kind];
        }

        $this->assertOk($response);

        return ['removed' => true, 'found' => true, 'email' => $email, 'kind' => $kind];
    }
}
