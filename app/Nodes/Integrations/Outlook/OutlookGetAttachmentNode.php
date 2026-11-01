<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookGetAttachmentNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_get_attachment';
    }

    public function name(): string
    {
        return 'Outlook: Get Attachment';
    }

    public function description(): string
    {
        return 'Downloads a file attached to a message as base64. Attached emails and cloud links have no file content.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'attachment_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'attachment_id' => Field::text('Attachment id', 'From List Attachments.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $file = $this->get($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/attachments/'.$this->pathId($config, 'attachment_id', 'The attachment'), $config);

        return ['id' => $file['id'] ?? null, 'name' => $file['name'] ?? null, 'content_type' => $file['contentType'] ?? null, 'size' => $file['size'] ?? null, 'content_base64' => $file['contentBytes'] ?? null];
    }
}
