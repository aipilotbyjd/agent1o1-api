<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\Concerns\BuildsMimeMessage;
use App\Nodes\Support\Field;

class GmailSendEmailNode extends AbstractGmailNode
{
    use BuildsMimeMessage;

    public function type(): string
    {
        return 'gmail_send_email';
    }

    public function name(): string
    {
        return 'Gmail: Send Email';
    }

    public function description(): string
    {
        return 'Sends an email via Gmail.';
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
                'bcc' => Field::emails('Bcc'),
                'reply_to' => Field::text('Reply-To', 'Where replies go, if not to you.', 'name@example.com'),
                'subject' => Field::text('Subject', null, 'Weekly report'),
                'body' => Field::textarea('Body'),
                'is_html' => Field::boolean('Send as HTML', 'Treat the body as HTML instead of plain text.'),
                'attachments' => Field::json('Attachments', 'A list of files: {"filename": "report.csv", "content": "a,b", "mime_type": "text/csv"}. Use "content_base64" for binary files.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $raw = $this->rawMessage([
            'To' => $this->addressList($config['to']),
            'Cc' => $this->addressList($config['cc'] ?? ''),
            'Bcc' => $this->addressList($config['bcc'] ?? ''),
            'Reply-To' => $this->addressList($config['reply_to'] ?? ''),
            'Subject' => (string) $config['subject'],
        ], (string) $config['body'], (bool) ($config['is_html'] ?? false), (array) ($config['attachments'] ?? []));

        return $this->post($run, '/users/me/messages/send', $config, ['raw' => $raw]);
    }
}
