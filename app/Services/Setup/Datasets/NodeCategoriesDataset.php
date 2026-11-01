<?php

namespace App\Services\Setup\Datasets;

use App\Enums\NodeCategory as NodeCategoryEnum;
use App\Enums\Setup\Ownership;
use App\Models\Nodes\NodeCategory;
use App\Services\Setup\AbstractSetupDataset;

/**
 * The core, built-in node categories: one row per `Enums\NodeCategory` case.
 * App/integration categories (`slack`, `github`, ...) are seeded alongside
 * their node families as those land (docs/NODES_CATALOG.md), not here.
 */
class NodeCategoriesDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'node-categories';
    }

    public function label(): string
    {
        return 'Core node categories';
    }

    public function ownership(): Ownership
    {
        return Ownership::CodeOwned;
    }

    public function minimumRows(): int
    {
        return 6;
    }

    public function rows(): int
    {
        return NodeCategory::query()->where('kind', 'core')->count();
    }

    public function apply(): void
    {
        $categories = require database_path('setup/node-categories.php');

        foreach ($categories as $category) {
            NodeCategory::query()->updateOrCreate(
                ['slug' => $category['enum']->value],
                [
                    'name' => $category['enum']->label(),
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'sort_order' => $category['sort_order'],
                    'kind' => 'core',
                ],
            );
        }
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/node-categories.php')];
    }

    /**
     * @return list<string>
     */
    public function definedSlugs(): array
    {
        return array_map(fn (array $category): string => $category['enum']->value, require database_path('setup/node-categories.php'));
    }

    public function problems(): array
    {
        $problems = [];
        $slugs = $this->definedSlugs();

        foreach ($this->duplicates($slugs) as $slug) {
            $problems[] = "Core category [{$slug}] appears more than once.";
        }

        foreach (NodeCategoryEnum::cases() as $case) {
            if (! in_array($case->value, $slugs, true)) {
                $problems[] = "Core category [{$case->value}] exists in the enum but has no entry.";
            }
        }

        foreach (require database_path('setup/node-categories.php') as $category) {
            foreach ($this->missing($category, ['icon', 'color', 'description', 'sort_order']) as $field) {
                $problems[] = "Core category [{$category['enum']->value}] has no {$field}.";
            }
        }

        return $problems;
    }
}
