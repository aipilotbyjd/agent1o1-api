<?php

namespace App\Nodes\Integrations\Resend;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ResendDeleteBroadcastNode extends AbstractResendNode
{
    public function type(): string
    {
        return 'resend_delete_broadcast';
    }

    public function name(): string
    {
        return 'Resend: Delete Broadcast';
    }

    public function description(): string
    {
        return 'Deletes a broadcast that has not been sent. This can\'t be undone.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['broadcast_id'],
            'properties' => [
                ...$this->credentialFields(),
                'broadcast_id' => Field::text('Broadcast id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->resend($run, $config, 'DELETE', '/broadcasts/'.$this->pathPart($config, 'broadcast_id', 'The broadcast id'));

        return ['deleted' => true, 'broadcast_id' => trim((string) $config['broadcast_id'])];
    }
}
