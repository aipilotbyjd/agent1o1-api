<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunGetTemplateNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_get_template';
    }

    public function name(): string
    {
        return 'Mailgun: Get Template';
    }

    public function description(): string
    {
        return 'Reads a stored template with its active version.';
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
                'template' => Field::dynamic('Template', 'mailgun.templates', null, 'welcome', allowCustom: true),
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $found = (array) $this->mailgun($run, $config, 'GET', "/v3/{$domain}/templates/".rawurlencode($this->requiredString($config, 'template', 'The template')), query: ['active' => 'yes']);
        $template = (array) ($found['template'] ?? []);

        return ['name' => $template['name'] ?? null, 'description' => $template['description'] ?? null, 'created_at' => $template['createdAt'] ?? null, 'version' => data_get($template, 'version.tag'), 'engine' => data_get($template, 'version.engine'), 'html' => data_get($template, 'version.template')];
    }
}
