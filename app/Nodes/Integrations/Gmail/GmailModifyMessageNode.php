<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailModifyMessageNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_modify_message';
    }

    public function name(): string
    {
        return 'Gmail: Modify Message';
    }

    public function description(): string
    {
        return 'Adds or removes labels on a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
                'add_label_ids' => Field::dynamic('Add labels', 'gmail.labels', multiple: true, type: 'array'),
                'remove_label_ids' => Field::dynamic('Remove labels', 'gmail.labels', 'Remove UNREAD to mark as read, INBOX to archive.', multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, "/users/me/messages/{$config['message_id']}/modify", $config, [
            'addLabelIds' => $config['add_label_ids'] ?? [],
            'removeLabelIds' => $config['remove_label_ids'] ?? [],
        ]);
    }
}
