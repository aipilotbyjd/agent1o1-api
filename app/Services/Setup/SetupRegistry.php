<?php

namespace App\Services\Setup;

use App\Services\Setup\Datasets\AgentTemplatesDataset;
use App\Services\Setup\Datasets\AppNodeCategoriesDataset;
use App\Services\Setup\Datasets\ConnectorsDataset;
use App\Services\Setup\Datasets\ModelCatalogDataset;
use App\Services\Setup\Datasets\ModelPricesDataset;
use App\Services\Setup\Datasets\NodeCategoriesDataset;
use App\Services\Setup\Datasets\PageTemplatesDataset;
use App\Services\Setup\Datasets\PlansDataset;
use App\Services\Setup\Datasets\ReferralProgramDataset;
use App\Services\Setup\Datasets\TemplateCollectionsDataset;
use App\Services\Setup\Datasets\TriggerPresetsDataset;
use App\Services\Setup\Datasets\WorkflowTemplatesDataset;
use InvalidArgumentException;
use LogicException;

/**
 * Every setup dataset, in the order they must be synced.
 */
class SetupRegistry
{
    /**
     * @var list<class-string<SetupDataset>>
     */
    private const array DATASETS = [
        PlansDataset::class,
        ReferralProgramDataset::class,
        NodeCategoriesDataset::class,
        AppNodeCategoriesDataset::class,
        TriggerPresetsDataset::class,
        ConnectorsDataset::class,
        ModelCatalogDataset::class,
        ModelPricesDataset::class,
        AgentTemplatesDataset::class,
        WorkflowTemplatesDataset::class,
        PageTemplatesDataset::class,
        TemplateCollectionsDataset::class,
    ];

    /**
     * @param  list<SetupDataset>|null  $datasets  Only for tests; defaults to the real list.
     */
    public function __construct(private ?array $datasets = null) {}

    /**
     * @return array<string, SetupDataset> keyed by dataset key, dependencies first
     */
    public function all(): array
    {
        $datasets = [];

        foreach ($this->datasets ?? array_map(fn (string $class): SetupDataset => app($class), self::DATASETS) as $dataset) {
            $datasets[$dataset->key()] = $dataset;
        }

        return $this->sorted($datasets);
    }

    /**
     * The named datasets plus everything they depend on, dependencies first.
     *
     * @param  list<string>  $keys
     * @return array<string, SetupDataset>
     */
    public function only(array $keys): array
    {
        $all = $this->all();

        foreach ($keys as $key) {
            if (! isset($all[$key])) {
                throw new InvalidArgumentException("Unknown setup dataset [{$key}]. Known: ".implode(', ', array_keys($all)).'.');
            }
        }

        $wanted = [];
        $collect = function (string $key) use (&$collect, &$wanted, $all): void {
            if (isset($wanted[$key])) {
                return;
            }

            $wanted[$key] = true;

            foreach ($all[$key]->dependencies() as $dependency) {
                $collect($dependency);
            }
        };

        foreach ($keys as $key) {
            $collect($key);
        }

        return array_intersect_key($all, $wanted);
    }

    /**
     * @param  array<string, SetupDataset>  $datasets
     * @return array<string, SetupDataset>
     */
    private function sorted(array $datasets): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (string $key) use (&$visit, &$sorted, &$visiting, $datasets): void {
            if (isset($sorted[$key])) {
                return;
            }

            if (isset($visiting[$key])) {
                throw new LogicException("Setup datasets depend on each other in a loop at [{$key}].");
            }

            if (! isset($datasets[$key])) {
                throw new LogicException("A setup dataset depends on [{$key}], which is not registered.");
            }

            $visiting[$key] = true;

            foreach ($datasets[$key]->dependencies() as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$key]);
            $sorted[$key] = $datasets[$key];
        };

        foreach (array_keys($datasets) as $key) {
            $visit($key);
        }

        return $sorted;
    }
}
