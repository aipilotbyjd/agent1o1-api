<?php

namespace App\Nodes\Integrations\DocuSign;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DocuSignListTemplatesNode extends AbstractDocuSignNode
{
    public function type(): string
    {
        return 'docusign_list_templates';
    }

    public function name(): string
    {
        return 'DocuSign: List Templates';
    }

    public function description(): string
    {
        return 'Lists your DocuSign templates with their IDs, to pick the one to send.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->accountFields(),
                'search' => Field::text('Search', 'Words in the template’s name.'),
                'limit' => Field::integer('Most templates', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->call($run, $config, 'GET', $this->root($run, $config).'/templates', query: [
            'search_text' => filled($config['search'] ?? null) ? trim((string) $config['search']) : null,
            'count' => max(1, min(100, (int) ($config['limit'] ?? 25))),
            'include' => 'recipients',
        ]);

        $templates = array_map(fn (mixed $template): array => [
            'template_id' => $template['templateId'] ?? null,
            'name' => $template['name'] ?? null,
            'description' => $template['description'] ?? null,
            'roles' => array_values(array_filter(array_map(fn (mixed $signer): ?string => $signer['roleName'] ?? null, (array) ($template['recipients']['signers'] ?? [])))),
            'updated_at' => $template['lastModified'] ?? null,
        ], (array) ($body['envelopeTemplates'] ?? []));

        return ['templates' => $templates, 'count' => count($templates)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'templates' => ['title' => 'Templates', 'description' => 'Each: template_id, name, description and the roles it expects.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
