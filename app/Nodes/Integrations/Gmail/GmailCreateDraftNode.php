<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\Concerns\BuildsMimeMessage;
use App\Nodes\Support\Field;

class GmailCreateDraftNode extends AbstractGmailNode
{
    use BuildsMimeMessage;

    public function type(): string
    {
        return 'gmail_create_draft';
    }

    public function name(): string
    {
        return 'Gmail: Create Draft';
    }

    public function description(): string
    {
        return 'Creates a draft email message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'subject' => Field::text('Subject'),
                'body' => Field::textarea('Body'),
                'is_html' => Field::boolean('Write as HTML', 'Treat the body as HTML instead of plain text.'),
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
            'Subject' => (string) $config['subject'],
        ], (string) $config['body'], (bool) ($config['is_html'] ?? false), (array) ($config['attachments'] ?? []));

        return $this->post($run, '/users/me/drafts', $config, ['message' => ['raw' => $raw]]);
    }
}
