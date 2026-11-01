<?php

namespace App\Nodes\Integrations\Outlook;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class OutlookMoveMessageNode extends AbstractOutlookNode
{
    public function type(): string
    {
        return 'outlook_move_message';
    }

    public function name(): string
    {
        return 'Outlook: Move Message';
    }

    public function description(): string
    {
        return 'Moves a message to another folder, e.g. archive.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'folder'],
            'properties' => [
                ...$this->credentialFields(),
                'message_id' => Field::dynamic('Message', 'outlook.messages', 'Pick a recent email, or map a message ID from an earlier step.'),
                'folder' => Field::dynamic('Move to folder', 'outlook.folders', 'A folder, or a well-known name: inbox, archive, junkemail, deleteditems.', 'archive'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/me/messages/'.rawurlencode((string) $config['message_id']).'/move', $config, [
            'destinationId' => $config['folder'],
        ]);
    }
}
