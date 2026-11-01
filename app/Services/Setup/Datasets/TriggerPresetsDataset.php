<?php

namespace App\Services\Setup\Datasets;

use App\Enums\Setup\Ownership;
use App\Models\Triggers\TriggerPreset;
use App\Services\Setup\AbstractSetupDataset;

/**
 * The catalog shown in the trigger picker. Priority 1 integrations from
 * docs/NODES_CATALOG.md (GitHub, Slack, Stripe) plus the two most common
 * schedule presets: the rest of the catalog grows alongside the connectors
 * that back it.
 */
class TriggerPresetsDataset extends AbstractSetupDataset
{
    public function key(): string
    {
        return 'trigger-presets';
    }

    public function label(): string
    {
        return 'Trigger presets';
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
        return TriggerPreset::query()->count();
    }

    public function apply(): void
    {
        foreach (require database_path('setup/trigger-presets.php') as $preset) {
            TriggerPreset::query()->updateOrCreate(['key' => $preset['key']], $preset);
        }
    }

    protected function dataFiles(): array
    {
        return [database_path('setup/trigger-presets.php')];
    }

    public function problems(): array
    {
        $presets = require database_path('setup/trigger-presets.php');
        $problems = [];
        $categories = ['schedule', ...app(NodeCategoriesDataset::class)->definedSlugs(), ...app(AppNodeCategoriesDataset::class)->definedSlugs()];

        foreach ($this->duplicates(array_column($presets, 'key')) as $key) {
            $problems[] = "Trigger preset [{$key}] appears more than once.";
        }

        foreach ($presets as $preset) {
            foreach ($this->missing($preset, ['key', 'name', 'category', 'type']) as $field) {
                $problems[] = 'Trigger preset ['.($preset['key'] ?? '?')."] has no {$field}.";
            }

            if (! in_array($preset['category'] ?? null, $categories, true)) {
                $problems[] = "Trigger preset [{$preset['key']}] is filed under [{$preset['category']}], which is not a category.";
            }
        }

        return $problems;
    }
}
