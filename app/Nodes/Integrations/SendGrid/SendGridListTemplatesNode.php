<?php

namespace App\Nodes\Integrations\SendGrid;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SendGridListTemplatesNode extends AbstractSendGridNode
{
    public function type(): string
    {
        return 'sendgrid_list_templates';
    }

    public function name(): string
    {
        return 'SendGrid: List Templates';
    }

    public function description(): string
    {
        return 'Lists the dynamic email templates.';
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
        $found = (array) $this->sendgrid($run, $config, 'GET', '/templates', query: ['generations' => 'dynamic', 'page_size' => 200]);
        $templates = array_map(fn (array $template): array => ['id' => $template['id'] ?? null, 'name' => $template['name'] ?? null, 'updated_at' => $template['updated_at'] ?? null], array_values(array_filter((array) ($found['result'] ?? $found['templates'] ?? []), 'is_array')));

        return ['templates' => $templates, 'count' => count($templates)];
    }
}
