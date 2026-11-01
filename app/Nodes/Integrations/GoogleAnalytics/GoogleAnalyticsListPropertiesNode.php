<?php

namespace App\Nodes\Integrations\GoogleAnalytics;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class GoogleAnalyticsListPropertiesNode extends AbstractGoogleAnalyticsNode
{
    public function type(): string
    {
        return 'google_analytics_list_properties';
    }

    public function name(): string
    {
        return 'Google Analytics: List Properties';
    }

    public function description(): string
    {
        return 'Lists the GA4 properties the account can see.';
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

    protected function baseUrl(): string
    {
        return 'https://analyticsadmin.googleapis.com/v1beta';
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->get($run, '/accountSummaries', $config, ['pageSize' => 200]);
        $properties = [];

        foreach ((array) ($response['accountSummaries'] ?? []) as $account) {
            foreach ((array) ($account['propertySummaries'] ?? []) as $property) {
                $properties[] = [
                    'property_id' => preg_replace('#^properties/#', '', (string) ($property['property'] ?? '')),
                    'name' => $property['displayName'] ?? null,
                    'account' => $account['displayName'] ?? null,
                ];
            }
        }

        return ['properties' => $properties];
    }
}
