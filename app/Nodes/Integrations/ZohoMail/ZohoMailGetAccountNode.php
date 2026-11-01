<?php

namespace App\Nodes\Integrations\ZohoMail;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoMailGetAccountNode extends AbstractZohoMailNode
{
    public function type(): string
    {
        return 'zoho_mail_get_account';
    }

    public function name(): string
    {
        return 'Zoho Mail: Get Account';
    }

    public function description(): string
    {
        return 'Reads the connected mail account and checks the connection works.';
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
        $id = $this->accountId($run, $config);
        $accounts = (array) ($this->mail($run, $config, 'GET', 'accounts')['data'] ?? []);
        $mine = collect($accounts)->first(fn (mixed $account): bool => is_array($account) && (string) ($account['accountId'] ?? '') === $id) ?? [];

        return ['id' => $id, 'email' => $mine['primaryEmailAddress'] ?? null, 'name' => $mine['displayName'] ?? null, 'account_count' => count($accounts)];
    }
}
