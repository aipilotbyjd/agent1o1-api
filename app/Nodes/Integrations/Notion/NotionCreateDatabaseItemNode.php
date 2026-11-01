<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionCreateDatabaseItemNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_create_database_item';
    }

    public function name(): string
    {
        return 'Notion: Create Database Item';
    }

    public function description(): string
    {
        return 'Adds an item (a row) to a database.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['database'],
            'properties' => [
                ...$this->credentialFields(),
                'database' => Field::dynamic('Database', 'notion.databases', 'Pick a database, or paste its link or id.'),
                'title' => Field::text('Title', 'The value of the database\'s title column.'),
                'values' => Field::keyValue('Column values', 'The column name and the plain value to set. Choices by name, several separated by commas, dates as 2026-03-01.'),
                'properties' => Field::json('Properties (JSON)', 'Notion property values, for column types the plain values above can\'t set (formulas excluded). Merged over the column values.'),
                'content' => Field::textarea('Content', 'Plain text for the body of the item. Every line becomes a paragraph.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $databaseId = $this->id($config, 'database', 'The database');
        $values = (array) ($config['values'] ?? []);
        $properties = $this->jsonField($config, 'properties', 'Properties');
        $title = trim((string) ($config['title'] ?? ''));

        if ($values !== [] || $title !== '') {
            $database = $this->get($run, "/databases/{$databaseId}", $config);

            if ($title !== '') {
                $values[$this->titleColumn($database)] = $title;
            }

            $properties = [...$this->propertiesFromValues($database, $values), ...$properties];
        }

        $blocks = $this->blocksFromText((string) ($config['content'] ?? ''), 'paragraph');

        $page = $this->post($run, '/pages', $config, array_filter([
            'parent' => ['database_id' => $databaseId],
            'properties' => $properties === [] ? new \stdClass : $properties,
            'children' => $blocks === [] ? null : array_slice($blocks, 0, self::APPEND_LIMIT),
        ], fn (mixed $value): bool => $value !== null));

        $this->appendInChunks($run, (string) $page['id'], $config, array_slice($blocks, self::APPEND_LIMIT));

        return $this->shapePage($page);
    }
}
