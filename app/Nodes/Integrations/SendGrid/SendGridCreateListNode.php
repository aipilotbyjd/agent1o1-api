<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SendGridCreateListNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_create_list';
    }

    public function name(): string
    {
        return 'SendGrid: Create List';
    }

    public function description(): string
    {
        return 'Creates a marketing list.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'Newsletter'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $list = (array) $this->sendgrid($run, $config, 'POST', '/marketing/lists', ['name' => $this->requiredString($config, 'name', 'The name')]);

        return ['id' => $list['id'] ?? null, 'name' => $list['name'] ?? null, 'contacts' => $list['contact_count'] ?? 0];
    }
}
