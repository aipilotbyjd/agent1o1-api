<?php

namespace App\Nodes\Integrations\Mailchimp;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MailchimpListTemplatesNode extends AbstractMailchimpNode
{
    public function type(): string
    {
        return 'mailchimp_list_templates';
    }

    public function name(): string
    {
        return 'Mailchimp: List Templates';
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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $templates = $this->listAll($run, $config, '/templates', 'templates', ['type' => 'user'], $this->limit($config, 50));

        return ['templates' => array_map(fn (array $template): array => ['id' => $template['id'] ?? null, 'name' => $template['name'] ?? null, 'type' => $template['type'] ?? null, 'category' => $template['category'] ?? null, 'created_at' => $template['date_created'] ?? null], $templates), 'count' => count($templates)];
    }
}
