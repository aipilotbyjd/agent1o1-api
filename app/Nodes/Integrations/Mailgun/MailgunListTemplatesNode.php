<?php

namespace App\Nodes\Integrations\Mailgun;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailgunListTemplatesNode extends AbstractMailgunNode
{
    public function type(): string
    {
        return 'mailgun_list_templates';
    }

    public function name(): string
    {
        return 'Mailgun: List Templates';
    }

    public function description(): string
    {
        return 'Lists the email templates stored for the domain.';
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
                'domain' => Field::advanced(Field::text('Domain', 'Another domain of the account. Empty uses the connection\'s.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($run, $config);
        $templates = $this->listAll($run, $config, "/v3/{$domain}/templates", [], $this->limit($config, 100), 100);

        return ['templates' => array_map(fn (array $template): array => ['name' => $template['name'] ?? null, 'description' => $template['description'] ?? null, 'created_at' => $template['createdAt'] ?? null], $templates), 'count' => count($templates)];
    }
}
