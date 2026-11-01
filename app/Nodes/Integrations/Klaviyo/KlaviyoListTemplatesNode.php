<?php

namespace App\Nodes\Integrations\Klaviyo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KlaviyoListTemplatesNode extends AbstractKlaviyoNode
{
    public function type(): string
    {
        return 'klaviyo_list_templates';
    }

    public function name(): string
    {
        return 'Klaviyo: List Email Templates';
    }

    public function description(): string
    {
        return 'Lists the account\'s email templates.';
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
        [$templates] = $this->listAll($run, $config, '/templates', [], 1000, 10);

        return ['templates' => array_map(fn (array $template): array => ['id' => $template['id'] ?? null, 'name' => data_get($template, 'attributes.name'), 'editor_type' => data_get($template, 'attributes.editor_type'), 'updated_at' => data_get($template, 'attributes.updated')], $templates), 'count' => count($templates)];
    }
}
