<?php

namespace App\Nodes\Integrations\Gmail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GmailCreateLabelNode extends AbstractGmailNode
{
    public function type(): string
    {
        return 'gmail_create_label';
    }

    public function name(): string
    {
        return 'Gmail: Create Label';
    }

    public function description(): string
    {
        return 'Creates a label. Use "Parent/Child" in the name to nest it.';
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
                'name' => Field::text('Label name', null, 'Clients/Acme'),
                'show_in_message_list' => Field::boolean('Show in the message list', 'Hide it to keep the label out of the way.', true),
                'show_in_label_list' => Field::select('Show in the sidebar', [
                    'labelShow' => 'Always',
                    'labelShowIfUnread' => 'Only with unread mail',
                    'labelHide' => 'Never',
                ], null, 'labelShow'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->post($run, '/users/me/labels', $config, [
            'name' => $config['name'],
            'messageListVisibility' => ($config['show_in_message_list'] ?? true) ? 'show' : 'hide',
            'labelListVisibility' => $config['show_in_label_list'] ?? 'labelShow',
        ]);
    }
}
