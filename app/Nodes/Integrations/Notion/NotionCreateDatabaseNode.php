<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionCreateDatabaseNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_create_database';
    }

    public function name(): string
    {
        return 'Notion: Create Database';
    }

    public function description(): string
    {
        return 'Creates a database inside a page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['parent_page', 'title'],
            'properties' => [
                ...$this->credentialFields(),
                'parent_page' => Field::dynamic('Parent page', 'notion.pages', 'The page to create it in. Pick one, or paste its link or id.'),
                'title' => Field::text('Title'),
                'properties' => Field::json('Columns (JSON)', 'Notion column definitions, e.g. {"Name": {"title": {}}, "Status": {"select": {}}}. Leave empty for a single Name column.'),
                'is_inline' => Field::boolean('Show inline on the page', default: false),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $properties = $this->jsonField($config, 'properties', 'Columns');

        // A database needs exactly one title column.
        if (! collect($properties)->contains(fn (mixed $column): bool => is_array($column) && array_key_exists('title', $column))) {
            $properties = ['Name' => ['title' => new \stdClass], ...$properties];
        }

        return $this->shapeDatabase($this->post($run, '/databases', $config, [
            'parent' => ['type' => 'page_id', 'page_id' => $this->id($config, 'parent_page', 'The parent page')],
            'title' => $this->richText((string) $config['title']),
            'properties' => $properties,
            'is_inline' => (bool) ($config['is_inline'] ?? false),
        ]));
    }
}
