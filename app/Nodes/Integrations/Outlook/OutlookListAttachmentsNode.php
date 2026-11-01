<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookListAttachmentsNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_list_attachments';
    }

    public function name(): string
    {
        return 'Outlook: List Attachments';
    }

    public function description(): string
    {
        return 'Lists the files attached to a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->get($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/attachments', $config, ['$select' => 'id,name,contentType,size,isInline']);
        $attachments = array_map(fn (array $file): array => ['id' => $file['id'] ?? null, 'name' => $file['name'] ?? null, 'content_type' => $file['contentType'] ?? null, 'size' => $file['size'] ?? null, 'inline' => (bool) ($file['isInline'] ?? false)], array_values(array_filter((array) ($found['value'] ?? []), 'is_array')));

        return ['attachments' => $attachments, 'count' => count($attachments)];
    }
}
