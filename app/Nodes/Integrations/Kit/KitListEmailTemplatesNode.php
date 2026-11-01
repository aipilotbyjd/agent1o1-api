<?php

namespace App\Nodes\Integrations\Kit;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class KitListEmailTemplatesNode extends AbstractKitNode
{
    public function type(): string
    {
        return 'kit_list_email_templates';
    }

    public function name(): string
    {
        return 'Kit: List Email Templates';
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
        $templates = $this->listAll($run, $config, '/email_templates', 'email_templates', [], 1000);

        return ['templates' => array_map(fn (array $template): array => ['id' => $template['id'] ?? null, 'name' => $template['name'] ?? null, 'is_default' => $template['is_default'] ?? false], $templates), 'count' => count($templates)];
    }
}
