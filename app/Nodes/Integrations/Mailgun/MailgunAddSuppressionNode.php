<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MailgunAddSuppressionNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_add_suppression';
    }

    public function name(): string
    {
        return 'Mailgun: Suppress Address';
    }

    public function description(): string
    {
        return 'Stops Mailgun sending to an address: as a bounce, an unsubscribe or a spam complaint.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'kind'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'ada@example.com'),
                'kind' => Field::select('As', ['bounces' => 'Bounce', 'unsubscribes' => 'Unsubscribe', 'complaints' => 'Spam complaint'], null, 'unsubscribes'),
                'reason' => Field::advanced(Field::text('Reason', 'For a bounce: why. For an unsubscribe: the tag it applies to (empty is all).')),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $kind = (string) ($config['kind'] ?? 'unsubscribes');
        $email = $this->email($config);
        $reason = $this->oneLine($config['reason'] ?? '');

        $form = match ($kind) {
            'bounces' => ['address' => $email, 'error' => $reason === '' ? null : $reason],
            'unsubscribes' => ['address' => $email, 'tag' => $reason === '' ? '*' : $reason],
            'complaints' => ['address' => $email],
            default => throw new InvalidArgumentException("\"{$kind}\" is not a suppression list."),
        };

        $this->mailgun($run, $config, 'POST', "/v3/{$domain}/{$kind}", $form);

        return ['suppressed' => true, 'email' => $email, 'kind' => $kind];
    }
}
