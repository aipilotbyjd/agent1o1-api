<?php

namespace App\Services\Setup;

use App\Enums\Setup\Ownership;

/**
 * One syncable slice of the data the app needs to work: its category list,
 * its connector catalog, its plans. Add a new one by implementing this and
 * listing it in `SetupRegistry`.
 */
interface SetupDataset
{
    /**
     * Stable identifier used by `--only`, the status table and `setup_syncs`.
     */
    public function key(): string;

    public function label(): string;

    public function ownership(): Ownership;

    /**
     * Bump to force a re-sync of an unchanged-looking dataset.
     */
    public function version(): int;

    /**
     * Keys of the datasets that must be synced first.
     *
     * @return list<string>
     */
    public function dependencies(): array;

    /**
     * Absolute paths of every file whose contents define this dataset; their
     * hash decides whether it changed since the last sync.
     *
     * @return list<string>
     */
    public function sources(): array;

    /**
     * Fewer rows than this means the dataset is missing or was damaged.
     */
    public function minimumRows(): int;

    public function rows(): int;

    /**
     * Mistakes in the definitions themselves (a duplicate key, a connector
     * with no app category), found by reading the data before anything is
     * written. Any problem stops the sync of this dataset.
     *
     * @return list<string>
     */
    public function problems(): array;

    /**
     * Write the dataset. Runs inside a transaction and must be safe to repeat.
     */
    public function apply(): void;
}
