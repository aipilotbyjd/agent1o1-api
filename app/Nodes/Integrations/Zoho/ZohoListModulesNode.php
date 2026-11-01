<?php

namespace App\Nodes\Integrations\Zoho;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ZohoListModulesNode extends AbstractZohoNode
{
    public function type(): string
    {
        return 'zoho_list_modules';
    }

    public function name(): string
    {
        return 'Zoho CRM: List Modules';
    }

    public function description(): string
    {
        return 'Lists the modules (Leads, Deals, your own) you can create records in.';
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
        $modules = array_values(array_filter((array) ($this->zoho($run, $config, 'GET', '/settings/modules')['modules'] ?? []), fn (mixed $module): bool => is_array($module) && ($module['api_supported'] ?? false) && ($module['creatable'] ?? false)));

        return ['modules' => array_map(fn (array $module): array => ['api_name' => $module['api_name'] ?? null, 'label' => $module['plural_label'] ?? null, 'custom' => ($module['generated_type'] ?? 'default') === 'custom'], $modules), 'count' => count($modules)];
    }
}
