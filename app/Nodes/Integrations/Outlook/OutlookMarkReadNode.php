<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookMarkReadNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_mark_read';
    }

    public function name(): string
    {
        return 'Outlook: Mark as Read or Unread';
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
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'state' => Field::select('Mark as', ['read' => 'Read', 'unread' => 'Unread'], null, 'read'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $read = ($config['state'] ?? 'read') !== 'unread';

        $this->patch($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message'), $config, ['isRead' => $read]);

        return ['message_id' => trim((string) $config['message_id']), 'is_read' => $read];
    }
}
