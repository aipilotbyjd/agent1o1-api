<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkListTemplatesNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_list_templates';
    }

    public function name(): string
    {
        return 'Postmark: List Templates';
    }

    public function description(): string
    {
        return 'Lists the email templates and layouts with their ids and aliases.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $templates = $this->listAll($run, $config, '/templates', 'Templates', [], $this->limit($config, 100), 100);

        return ['templates' => array_map(fn (array $template): array => ['id' => $template['TemplateId'] ?? null, 'name' => $template['Name'] ?? null, 'alias' => $template['Alias'] ?? null, 'type' => $template['TemplateType'] ?? null, 'active' => $template['Active'] ?? null], $templates), 'count' => count($templates)];
    }
}
