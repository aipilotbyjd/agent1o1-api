<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailGetAttachmentNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_get_attachment';
    }

    public function name(): string
    {
        return 'Gmail: Get Attachment';
    }

    public function description(): string
    {
        return 'Downloads one attachment of an email as base64. Get Message lists the attachment IDs.';
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
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
                'attachment_id' => Field::text('Attachment ID', 'The "attachmentId" of a part in Get Message\'s payload.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $attachment = $this->get(
            $run,
            '/users/me/messages/'.rawurlencode((string) $config['message_id']).'/attachments/'.rawurlencode((string) $config['attachment_id']),
            $config,
        );

        return [
            'size' => (int) ($attachment['size'] ?? 0),
            // Gmail sends base64url; hand on ordinary base64, which every consumer reads.
            'content_base64' => strtr((string) ($attachment['data'] ?? ''), '-_', '+/'),
        ];
    }
}
