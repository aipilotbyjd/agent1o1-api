<?php

namespace App\Nodes\Integrations\DropboxSign;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DropboxSignListTemplatesNode extends AbstractDropboxSignNode
{
    public function type(): string
    {
        return 'dropbox_sign_list_templates';
    }

    public function name(): string
    {
        return 'Dropbox Sign: List Templates';
    }

    public function description(): string
    {
        return 'Lists your Dropbox Sign templates with their IDs and the roles each expects, to pick the one to send.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'search' => Field::text('Search', 'Words in the template’s title.'),
                'limit' => Field::integer('Most templates', null, 25, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->call($run, $config, 'GET', self::API_URL.'/template/list', query: [
            'page_size' => max(1, min(100, (int) ($config['limit'] ?? 25))),
            'query' => filled($config['search'] ?? null) ? 'title:'.trim((string) $config['search']) : null,
        ]);

        $templates = array_map(fn (mixed $template): array => [
            'template_id' => $template['template_id'] ?? null,
            'title' => $template['title'] ?? null,
            'message' => $template['message'] ?? null,
            'roles' => array_values(array_filter(array_map(fn (mixed $role): ?string => $role['name'] ?? null, (array) ($template['signer_roles'] ?? [])))),
            'is_creator' => (bool) ($template['is_creator'] ?? false),
        ], (array) ($body['templates'] ?? []));

        return ['templates' => $templates, 'count' => count($templates)];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'templates' => ['title' => 'Templates', 'description' => 'Each: template_id, title, message and the roles it expects.'],
                'count' => ['title' => 'Count', 'description' => 'How many were returned.'],
            ],
        ];
    }
}
