<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Gmail\Concerns\BuildsMimeMessage;
use App\Nodes\Support\Field;

class GmailUpdateDraftNode extends AbstractGmailNode
{
    use BuildsMimeMessage;

    public function type(): string
    {
        return 'gmail_update_draft';
    }

    public function name(): string
    {
        return 'Gmail: Update Draft';
    }

    public function description(): string
    {
        return 'Replaces a draft\'s recipients, subject and body.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['draft_id', 'to', 'subject', 'body'],
            'properties' => [
                ...$this->credentialFields(),
                'draft_id' => Field::text('Draft id', 'From List Drafts or Create Draft.'),
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

        $draft = $this->put($run, '/users/me/drafts/'.$this->pathId($config, 'draft_id', 'The draft'), $config, ['message' => ['raw' => $raw]]);

        return ['updated' => true, 'draft_id' => $draft['id'] ?? $config['draft_id'], 'message_id' => $draft['message']['id'] ?? null];
    }
}
