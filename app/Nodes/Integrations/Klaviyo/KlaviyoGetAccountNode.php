<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoGetAccountNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_get_account';
    }

    public function name(): string
    {
        return 'Klaviyo: Get Account';
    }

    public function description(): string
    {
        return 'Gets the Klaviyo account: its name, timezone, currency and public key.';
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
        $account = (array) data_get((array) $this->klaviyo($run, $config, 'GET', '/accounts/'), 'data.0', []);

        return [
            'id' => $account['id'] ?? null,
            'organization' => data_get($account, 'attributes.contact_information.organization_name'),
            'timezone' => data_get($account, 'attributes.timezone'),
            'currency' => data_get($account, 'attributes.preferred_currency'),
            'public_api_key' => data_get($account, 'attributes.public_api_key'),
            'default_sender_email' => data_get($account, 'attributes.contact_information.default_sender_email'),
        ];
    }
}
