<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SendGridAddGlobalUnsubscribeNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_add_global_unsubscribe';
    }

    public function name(): string
    {
        return 'SendGrid: Add Global Unsubscribe';
    }

    public function description(): string
    {
        return 'Stops SendGrid emailing these addresses, whatever the email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['emails'],
            'properties' => [
                ...$this->credentialFields(),
                'emails' => Field::emails('Emails'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $emails = $this->emails($config, 'emails');

        if ($emails === [] || count($emails) > 1000) {
            throw new InvalidArgumentException('Give between 1 and 1000 email addresses.');
        }

        $this->sendgrid($run, $config, 'POST', '/asm/suppressions/global', ['recipient_emails' => $emails]);

        return ['unsubscribed' => count($emails), 'emails' => $emails];
    }
}
