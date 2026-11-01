<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridDeleteListNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_delete_list';
    }

    public function name(): string
    {
        return 'SendGrid: Delete List';
    }

    public function description(): string
    {
        return 'Deletes a marketing list, and optionally the contacts on it. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['list_id'],
            'properties' => [
                ...$this->credentialFields(),
                'list_id' => $this->listField(),
                'delete_contacts' => Field::advanced(Field::boolean('Delete the contacts too', 'Otherwise they stay in SendGrid, just off the list.', false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->identifier($config, 'list_id', 'The list');

        $this->sendgrid($run, $config, 'DELETE', '/marketing/lists/'.rawurlencode($id), query: ['delete_contacts' => ($config['delete_contacts'] ?? false) ? 'true' : 'false']);

        return ['deleted' => true, 'list_id' => $id];
    }
}
