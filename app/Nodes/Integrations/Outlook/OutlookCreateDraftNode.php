<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookCreateDraftNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_create_draft';
    }

    public function name(): string
    {
        return 'Outlook: Create Draft';
    }

    public function description(): string
    {
        return 'Creates a draft email in the Drafts folder.';
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
                'subject' => Field::text('Subject'),
                'body' => Field::textarea('Body'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/me/messages', $config, [
            'subject' => $config['subject'],
            'body' => ['contentType' => 'Text', 'content' => $config['body']],
            'toRecipients' => $this->recipients($config['to']),
            'ccRecipients' => $this->recipients($config['cc'] ?? null),
        ]);
    }
}
