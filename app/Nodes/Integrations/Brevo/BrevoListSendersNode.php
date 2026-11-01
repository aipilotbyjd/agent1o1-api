<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoListSendersNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_list_senders';
    }

    public function name(): string
    {
        return 'Brevo: List Senders';
    }

    public function description(): string
    {
        return 'Lists the verified senders emails can be sent from.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $senders = array_values(array_filter((array) data_get((array) $this->brevo($run, $config, 'GET', '/senders'), 'senders', []), 'is_array'));

        return ['senders' => array_map(fn (array $sender): array => ['id' => $sender['id'] ?? null, 'name' => $sender['name'] ?? null, 'email' => $sender['email'] ?? null, 'active' => $sender['active'] ?? null], $senders), 'count' => count($senders)];
    }
}
