<?php

namespace App\Nodes\Integrations\Postmark;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PostmarkGetTemplateNode extends AbstractPostmarkNode
{
    public function type(): string
    {
        return 'postmark_get_template';
    }

    public function name(): string
    {
        return 'Postmark: Get Template';
    }

    public function description(): string
    {
        return 'Reads a template: its subject and bodies, and the values it expects.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['template'],
            'properties' => [
                ...$this->credentialFields(),
                'template' => Field::dynamic('Template', 'postmark.templates', null, 'welcome', allowCustom: true),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $template = (array) $this->postmark($run, $config, 'GET', '/templates/'.$this->pathPart($config, 'template', 'The template'));

        return ['id' => $template['TemplateId'] ?? null, 'name' => $template['Name'] ?? null, 'alias' => $template['Alias'] ?? null, 'subject' => $template['Subject'] ?? null, 'html' => $template['HtmlBody'] ?? null, 'text' => $template['TextBody'] ?? null, 'type' => $template['TemplateType'] ?? null, 'active' => $template['Active'] ?? null, 'associated_server_id' => $template['AssociatedServerId'] ?? null];
    }
}
