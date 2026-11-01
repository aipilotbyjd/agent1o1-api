<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookSendEmailNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_send_email';
    }

    public function name(): string
    {
        return 'Outlook: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email from the connected Outlook account.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'subject', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'to' => Field::emails('To'),
                'cc' => Field::emails('Cc'),
                'subject' => Field::text('Subject'),
                'body' => Field::textarea('Body'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/me/sendMail', $config, [
            'message' => [
                'subject' => $config['subject'],
                'body' => ['contentType' => 'Text', 'content' => $config['body']],
                'toRecipients' => $this->recipients($config['to']),
                'ccRecipients' => $this->recipients($config['cc'] ?? null),
            ],
        ]);
    }
}
