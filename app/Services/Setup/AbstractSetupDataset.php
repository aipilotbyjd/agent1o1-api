<?php

namespace App\Services\Setup;

use ReflectionClass;

/**
 * Defaults shared by the datasets. A dataset's checksum covers its own class
 * file plus any data files it loads from `database/setup`.
 */
abstract class AbstractSetupDataset implements SetupDataset
{
    public function version(): int
    {
        return 1;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function minimumRows(): int
    {
        return 1;
    }

    public function problems(): array
    {
        return [];
    }

    /**
     * The values that appear more than once, for "duplicate key" problems.
     *
     * @param  array<int, string>  $values
     * @return list<string>
     */
    protected function duplicates(array $values): array
    {
        return array_values(array_keys(array_filter(array_count_values($values), fn (int $count): bool => $count > 1)));
    }

    /**
     * The names of `$required` that are missing or blank in `$row`.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $required
     * @return list<string>
     */
    protected function missing(array $row, array $required): array
    {
        return array_values(array_filter($required, fn (string $field): bool => blank($row[$field] ?? null)));
    }

    /**
     * Files outside this class whose contents change what it writes.
     *
     * @return list<string>
     */
    protected function dataFiles(): array
    {
        return [];
    }

    public function sources(): array
    {
        return [(new ReflectionClass($this))->getFileName(), ...$this->dataFiles()];
    }
}
