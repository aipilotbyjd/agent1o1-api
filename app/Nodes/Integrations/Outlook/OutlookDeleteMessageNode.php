<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookDeleteMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_delete_message';
    }

    public function name(): string
    {
        return 'Outlook: Delete Message';
    }

    public function description(): string
    {
        return 'Deletes a message.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        return $this->delete($run, '/me/messages/'.rawurlencode((string) $config['message_id']), $config);
    }
}
