<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class PostmarkListSendersNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_list_senders';
    }

    public function name(): string
    {
        return 'Postmark: List Sender Signatures';
    }

    public function description(): string
    {
        return 'Lists the confirmed sender signatures. Needs the Account API token on the connection.';
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
        $found = (array) $this->postmark($run, $config, 'GET', '/senders', query: ['count' => 500, 'offset' => 0], account: true);
        $senders = array_values(array_filter((array) ($found['SenderSignatures'] ?? []), 'is_array'));

        return ['senders' => array_map(fn (array $sender): array => ['id' => $sender['ID'] ?? null, 'email' => $sender['EmailAddress'] ?? null, 'name' => $sender['Name'] ?? null, 'confirmed' => $sender['Confirmed'] ?? null, 'domain' => $sender['Domain'] ?? null], $senders), 'count' => count($senders)];
    }
}
