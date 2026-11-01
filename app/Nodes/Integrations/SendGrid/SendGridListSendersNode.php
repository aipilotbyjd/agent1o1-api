<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SendGridListSendersNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_list_senders';
    }

    public function name(): string
    {
        return 'SendGrid: List Senders';
    }

    public function description(): string
    {
        return 'Lists the verified senders you can send from.';
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
        $found = (array) $this->sendgrid($run, $config, 'GET', '/verified_senders');
        $senders = array_map(fn (array $sender): array => ['id' => $sender['id'] ?? null, 'email' => $sender['from_email'] ?? null, 'name' => $sender['from_name'] ?? null, 'reply_to' => $sender['reply_to'] ?? null, 'verified' => $sender['verified'] ?? false], array_values(array_filter((array) ($found['results'] ?? []), 'is_array')));

        return ['senders' => $senders, 'count' => count($senders)];
    }
}
