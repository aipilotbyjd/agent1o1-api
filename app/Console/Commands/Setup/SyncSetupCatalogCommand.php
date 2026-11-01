<?php

namespace App\Console\Commands\Setup;

use App\Enums\Setup\SyncStatus;
use App\Services\Setup\SetupSyncer;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class SyncSetupCatalogCommand extends Command
{
    protected $signature = 'setup:catalog:sync
        {--only=* : Sync just these datasets (and what they depend on)}
        {--force : Re-apply even datasets that look up to date}
        {--dry-run : Show what would be applied without writing anything}';

    protected $description = 'Bring the setup data (plans, categories, connectors, models, templates) up to date with the code.';

    public function handle(SetupSyncer $syncer): int
    {
        try {
            $results = $syncer->sync(
                only: $this->keys(),
                force: (bool) $this->option('force'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Dataset', 'Result', 'Rows', 'Note'],
            array_map(fn ($result): array => [
                $result->dataset,
                $result->status->value,
                $result->rowsBefore === $result->rowsAfter ? (string) $result->rowsAfter : "{$result->rowsBefore} → {$result->rowsAfter}",
                $result->message ?? '',
            ], $results),
        );

        $failed = collect($results)->contains(fn ($result): bool => in_array($result->status, [SyncStatus::Failed, SyncStatus::Skipped], true));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        return collect($this->option('only'))
            ->flatMap(fn (string $value): array => explode(',', $value))
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->values()
            ->all();
    }
}
