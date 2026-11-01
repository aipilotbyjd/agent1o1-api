<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionUpdateDatabaseNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_update_database';
    }

    public function name(): string
    {
        return 'Notion: Update Database';
    }

    public function description(): string
    {
        return 'Renames a database, changes its description, or adds, renames and removes columns.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['database'],
            'properties' => [
                ...$this->credentialFields(),
                'database' => Field::dynamic('Database', 'notion.databases', 'Pick a database, or paste its link or id.'),
                'title' => Field::text('Title', 'A new title. Leave empty to keep it.'),
                'description' => Field::textarea('Description', 'A new description. Leave empty to keep it.'),
                'properties' => Field::json('Columns (JSON)', 'Columns to add or change, e.g. {"Priority": {"select": {}}}; set a column to null to remove it, or give {"name": "New name"} to rename it.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $properties = $this->jsonField($config, 'properties', 'Columns');
        $title = trim((string) ($config['title'] ?? ''));
        $description = trim((string) ($config['description'] ?? ''));

        if ($properties === [] && $title === '' && $description === '') {
            throw new \InvalidArgumentException('Notion: give a title, a description or columns to change.');
        }

        return $this->shapeDatabase($this->patch($run, '/databases/'.$this->id($config, 'database', 'The database'), $config, array_filter([
            'title' => $title === '' ? null : $this->richText($title),
            'description' => $description === '' ? null : $this->richText($description),
            'properties' => $properties === [] ? null : $properties,
        ], fn (mixed $value): bool => $value !== null)));
    }
}
