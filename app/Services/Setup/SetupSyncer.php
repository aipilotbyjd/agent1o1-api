<?php

namespace App\Services\Setup;

use App\Enums\Setup\DatasetState;
use App\Enums\Setup\SyncStatus;
use App\Models\System\SetupSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Brings the setup datasets up to date with the code, in dependency order,
 * and records what it did. A dataset whose code and rows are already in sync
 * is skipped without a single write.
 */
class SetupSyncer
{
    private const string LOCK = 'setup:catalog:sync';

    public function __construct(private readonly SetupRegistry $registry) {}

    /**
     * @param  list<string>  $only  Dataset keys; their dependencies come along.
     * @return list<SetupSyncResult>
     */
    public function sync(array $only = [], bool $force = false, bool $dryRun = false): array
    {
        $datasets = $only === [] ? $this->registry->all() : $this->registry->only($only);

        if ($dryRun) {
            return $this->run($datasets, $force, true);
        }

        $lock = Cache::lock(self::LOCK, 900);

        if (! $lock->get()) {
            throw new RuntimeException('Another setup sync is already running.');
        }

        try {
            return $this->run($datasets, $force, false);
        } finally {
            $lock->release();
        }
    }

    public function inspect(SetupDataset $dataset): DatasetState
    {
        $record = SetupSync::query()->where('dataset', $dataset->key())->first();

        return match (true) {
            $record === null => DatasetState::NeverSynced,
            $record->status === SyncStatus::Failed => DatasetState::Failed,
            $dataset->rows() < $dataset->minimumRows() => DatasetState::Incomplete,
            $record->checksum !== $this->checksum($dataset) => DatasetState::OutOfDate,
            default => DatasetState::InSync,
        };
    }

    public function checksum(SetupDataset $dataset): string
    {
        $parts = [$dataset->key(), (string) $dataset->version()];

        foreach ($dataset->sources() as $path) {
            $parts[] = is_file($path) ? md5_file($path) : 'missing:'.$path;
        }

        return sha1(implode('|', $parts));
    }

    /**
     * @param  array<string, SetupDataset>  $datasets
     * @return list<SetupSyncResult>
     */
    private function run(array $datasets, bool $force, bool $dryRun): array
    {
        $results = [];
        $failed = [];

        foreach ($datasets as $key => $dataset) {
            $blockedBy = array_values(array_intersect($dataset->dependencies(), $failed));

            if ($blockedBy !== []) {
                $failed[] = $key;
                $results[] = new SetupSyncResult($key, SyncStatus::Skipped, message: 'Depends on '.implode(', ', $blockedBy).', which failed.');

                continue;
            }

            if ($dryRun && ($problems = $dataset->problems()) !== []) {
                $failed[] = $key;
                $results[] = new SetupSyncResult($key, SyncStatus::Failed, $dataset->rows(), $dataset->rows(), implode(' ', $problems));

                continue;
            }

            if (! $force && ! $this->inspect($dataset)->needsSync()) {
                $rows = $dataset->rows();
                $results[] = new SetupSyncResult($key, SyncStatus::Unchanged, $rows, $rows);

                continue;
            }

            if ($dryRun) {
                $results[] = new SetupSyncResult($key, SyncStatus::Pending, $dataset->rows(), $dataset->rows(), $this->inspect($dataset)->value);

                continue;
            }

            $result = $this->apply($dataset);

            if ($result->status === SyncStatus::Failed) {
                $failed[] = $key;
            }

            $results[] = $result;
        }

        return $results;
    }

    private function apply(SetupDataset $dataset): SetupSyncResult
    {
        $before = $dataset->rows();
        $checksum = $this->checksum($dataset);

        $problems = $dataset->problems();

        if ($problems !== []) {
            $message = implode(' ', $problems);
            $this->record($dataset, $checksum, SyncStatus::Failed, $before, $before, $message);

            return new SetupSyncResult($dataset->key(), SyncStatus::Failed, $before, $before, $message);
        }

        try {
            DB::transaction(fn () => $dataset->apply());
        } catch (Throwable $exception) {
            $this->record($dataset, $checksum, SyncStatus::Failed, $before, $before, $exception->getMessage());

            return new SetupSyncResult($dataset->key(), SyncStatus::Failed, $before, $before, $exception->getMessage());
        }

        $after = $dataset->rows();

        if ($after < $dataset->minimumRows()) {
            $message = "Only {$after} row(s) after syncing; expected at least {$dataset->minimumRows()}.";
            $this->record($dataset, $checksum, SyncStatus::Failed, $before, $after, $message);

            return new SetupSyncResult($dataset->key(), SyncStatus::Failed, $before, $after, $message);
        }

        $this->record($dataset, $checksum, SyncStatus::Applied, $before, $after, null);

        return new SetupSyncResult($dataset->key(), SyncStatus::Applied, $before, $after);
    }

    private function record(SetupDataset $dataset, string $checksum, SyncStatus $status, int $before, int $after, ?string $error): void
    {
        SetupSync::query()->updateOrCreate(
            ['dataset' => $dataset->key()],
            [
                'checksum' => $checksum,
                'version' => $dataset->version(),
                'status' => $status,
                'rows_before' => $before,
                'rows_after' => $after,
                'error' => $error,
                'synced_at' => now(),
            ],
        );
    }
}
