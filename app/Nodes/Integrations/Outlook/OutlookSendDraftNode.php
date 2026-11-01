<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookSendDraftNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_send_draft';
    }

    public function name(): string
    {
        return 'Outlook: Send Draft';
    }

    public function description(): string
    {
        return 'Sends a draft made with Create Draft.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::text('Draft id', 'The id Create Draft returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->post($run, '/me/messages/'.$this->pathId($config, 'message_id', 'The message').'/send', $config);

        return ['sent' => true, 'message_id' => trim((string) $config['message_id'])];
    }
}
