<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class KitUpdateBroadcastNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_update_broadcast';
    }

    public function name(): string
    {
        return 'Kit: Update Broadcast';
    }

    public function description(): string
    {
        return 'Changes a broadcast that has not been sent yet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['broadcast_id', 'subject', 'content'],
            'properties' => [
                ...$this->credentialFields(),
                'broadcast_id' => Field::text('Broadcast id', null, '123'),

                'subject' => Field::text('Subject', null, 'March news'),
                'content' => Field::textarea('Content (HTML)', 'The email body. Kit adds the unsubscribe link.'),
                'preview_text' => Field::advanced(Field::text('Preview text')),
                'description' => Field::advanced(Field::text('Internal description')),
                'send_at' => Field::datetime('Send at', 'Leave empty to keep it as a draft.'),
                'tag_ids' => Field::advanced(Field::list('Only send to people with these tag ids', 'Leave empty to send to all subscribers.', '123')),
                'public' => Field::advanced(Field::boolean('Publish on your Kit page')),
                'email_template_id' => Field::advanced(Field::dynamic('Template', 'kit.templates', null, 'Default', type: 'integer')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'broadcast_id', 'The broadcast id');

        return $this->shapeBroadcast((array) data_get((array) $this->kit($run, $config, 'PUT', "/broadcasts/{$id}", $this->broadcastBody($config)), 'broadcast', []));
    }
}
