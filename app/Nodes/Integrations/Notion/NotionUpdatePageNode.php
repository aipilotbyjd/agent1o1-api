<?php

namespace App\Nodes\Integrations\Notion;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class NotionUpdatePageNode extends AbstractNotionNode
{
    public function type(): string
    {
        return 'notion_update_page';
    }

    public function name(): string
    {
        return 'Notion: Update Page or Database Item';
    }

    public function description(): string
    {
        return 'Changes the properties or icon of a page or a database item.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page'],
            'properties' => [
                ...$this->credentialFields(),
                'page' => Field::dynamic('Page', 'notion.pages', 'Pick a page, or paste its link or id.'),
                'title' => Field::text('Title', 'A new title. Leave empty to keep it.'),
                'values' => Field::keyValue('Column values', 'For a database item: the column name and the plain value to set. Choices by name, several separated by commas, dates as 2026-03-01.'),
                'properties' => Field::json('Properties (JSON)', 'Notion property values, for column types the plain values above can\'t set (formulas excluded). Merged over the column values.'),
                'icon' => Field::advanced(Field::text('Icon', 'An emoji for the page icon.', '📝')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $pageId = $this->id($config, 'page', 'The page');
        $values = (array) ($config['values'] ?? []);
        $properties = $this->jsonField($config, 'properties', 'Properties');
        $title = trim((string) ($config['title'] ?? ''));

        if ($values !== [] || $title !== '') {
            $current = $this->get($run, "/pages/{$pageId}", $config);

            if (isset($current['parent']['database_id']) || $values !== []) {
                // A database item's columns decide how plain values are written (and which column is the title).
                $database = $this->databaseOfPage($run, $current, $config);

                if ($title !== '') {
                    $values[$this->titleColumn($database)] = $title;
                }

                $properties = [...$this->propertiesFromValues($database, $values), ...$properties];
            } else {
                $properties = ['title' => ['title' => $this->richText($title)], ...$properties];
            }
        }

        $icon = trim((string) ($config['icon'] ?? ''));

        if ($properties === [] && $icon === '') {
            throw new \InvalidArgumentException('Notion: give a title, column values, properties or an icon to change.');
        }

        return $this->shapePage($this->patch($run, "/pages/{$pageId}", $config, array_filter([
            'properties' => $properties === [] ? null : $properties,
            'icon' => $icon === '' ? null : ['type' => 'emoji', 'emoji' => $icon],
        ], fn (mixed $value): bool => $value !== null)));
    }
}
