<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class MailchimpGetAccountNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_get_account';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Account';
    }

    public function description(): string
    {
        return 'Gets the account\'s name, plan and totals.';
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
        $account = (array) $this->mailchimp($run, $config, 'GET', '/', query: ['fields' => 'account_name,email,role,pro_enabled,total_subscribers,first_name,last_name,login_id']);

        return ['account_name' => $account['account_name'] ?? null, 'email' => $account['email'] ?? null, 'role' => $account['role'] ?? null, 'plan_is_pro' => $account['pro_enabled'] ?? null, 'total_subscribers' => $account['total_subscribers'] ?? null];
    }
}
