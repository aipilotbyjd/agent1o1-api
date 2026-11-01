<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailAddLabelNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_add_label';
    }

    public function name(): string
    {
        return 'Gmail: Add Label';
    }

    public function description(): string
    {
        return 'Adds labels to a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'label_ids'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'gmail.messages', 'Pick a recent email, or map a message ID from an earlier step.', '18c2f0a1b2c3d4e5'),
                'label_ids' => Field::dynamic('Labels', 'gmail.labels', multiple: true, type: 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, "/users/me/messages/{$config['message_id']}/modify", $config, [
            'addLabelIds' => (array) $config['label_ids'],
            'removeLabelIds' => [],
        ]);
    }
}
