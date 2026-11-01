<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailMarkReadNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_mark_read';
    }

    public function name(): string
    {
        return 'Gmail: Mark as Read or Unread';
    }

    public function description(): string
    {
        return 'Marks a message read or unread.';
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
                'state' => Field::select('Mark as', ['read' => 'Read', 'unread' => 'Unread'], null, 'read'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $read = ($config['state'] ?? 'read') !== 'unread';

        $message = $this->post($run, '/users/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/modify', $config, $read ? ['removeLabelIds' => ['UNREAD']] : ['addLabelIds' => ['UNREAD']]);

        return ['message_id' => $message['id'] ?? $config['message_id'], 'is_read' => $read, 'label_ids' => $message['labelIds'] ?? []];
    }
}
