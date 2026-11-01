<?php

namespace App\Services\SuperAdmin\System\Checks;

use App\Enums\Setup\DatasetState;
use App\Services\Setup\SetupRegistry;
use App\Services\Setup\SetupSyncer;

/**
 * Whether the setup data (plans, categories, connectors, models, templates)
 * has been loaded and matches the code. Without it the editor is empty, so a
 * missing or failed dataset is a problem, and a stale one is a warning.
 */
class SetupDataCheck implements HealthCheck
{
    public function __construct(
        private readonly SetupRegistry $registry,
        private readonly SetupSyncer $syncer,
    ) {}

    public function name(): string
    {
        return 'setup_data';
    }

    public function run(): array
    {
        $states = [];

        foreach ($this->registry->all() as $key => $dataset) {
            $states[$key] = $this->syncer->inspect($dataset);
        }

        $broken = array_keys(array_filter($states, fn (DatasetState $state): bool => $state !== DatasetState::InSync && $state !== DatasetState::OutOfDate));
        $stale = array_keys(array_filter($states, fn (DatasetState $state): bool => $state === DatasetState::OutOfDate));

        $details = array_map(fn (DatasetState $state): string => $state->value, $states);

        return match (true) {
            $broken !== [] => [
                'status' => self::DOWN,
                'message' => 'Setup data is missing or failed for: '.implode(', ', $broken).'. Run `php artisan setup:catalog:sync`.',
                'details' => $details,
            ],
            $stale !== [] => [
                'status' => self::DEGRADED,
                'message' => 'Setup data is out of date for: '.implode(', ', $stale).'. Run `php artisan setup:catalog:sync`.',
                'details' => $details,
            ],
            default => [
                'status' => self::HEALTHY,
                'message' => 'All '.count($states).' setup datasets are in sync.',
                'details' => $details,
            ],
        };
    }
}
