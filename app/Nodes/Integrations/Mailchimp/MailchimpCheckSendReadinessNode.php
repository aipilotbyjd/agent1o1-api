<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpCheckSendReadinessNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_check_send_readiness';
    }

    public function name(): string
    {
        return 'Mailchimp: Check Campaign Is Ready';
    }

    public function description(): string
    {
        return 'Runs Mailchimp\'s send checklist on a draft: is it ready to send, and if not, what is missing.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['campaign_id'],
            'properties' => [
                ...$this->credentialFields(),
                'campaign_id' => $this->campaignField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->campaignId($config);
        $checklist = (array) $this->mailchimp($run, $config, 'GET', "/campaigns/{$id}/send-checklist");
        $problems = array_values(array_map(fn (array $item): string => trim(($item['heading'] ?? '').': '.($item['details'] ?? ''), ': '), array_filter((array) ($checklist['items'] ?? []), fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'error')));

        return ['campaign_id' => $id, 'ready' => (bool) ($checklist['is_ready'] ?? false), 'problems' => $problems];
    }
}
