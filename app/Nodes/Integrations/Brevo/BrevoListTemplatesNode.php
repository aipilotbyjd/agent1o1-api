<?php

namespace App\Nodes\Integrations\Brevo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class BrevoListTemplatesNode extends AbstractBrevoNode
{
    public function type(): string
    {
        return 'brevo_list_templates';
    }

    public function name(): string
    {
        return 'Brevo: List Email Templates';
    }

    public function description(): string
    {
        return 'Lists the transactional email templates.';
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
        $templates = $this->listAll($run, $config, '/smtp/templates', 'templates', ['templateStatus' => 'true'], 1000, 100);

        return ['templates' => array_map(fn (array $template): array => ['id' => $template['id'] ?? null, 'name' => $template['name'] ?? null, 'subject' => $template['subject'] ?? null, 'active' => $template['isActive'] ?? null], $templates), 'count' => count($templates)];
    }
}
