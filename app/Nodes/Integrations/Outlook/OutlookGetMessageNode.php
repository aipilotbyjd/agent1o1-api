<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookGetMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_get_message';
    }

    public function name(): string
    {
        return 'Outlook: Get Message';
    }

    public function description(): string
    {
        return 'Gets one message, with its plain-text body.';
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
        return $this->get($run, '/me/messages/'.rawurlencode((string) $config['message_id']), $config);
    }
}
