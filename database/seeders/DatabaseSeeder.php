<?php

namespace Database\Seeders;

use App\Services\Setup\SetupSyncer;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Runs the setup sync (the same as `php artisan setup:catalog:sync`).
     * No fake users or workspaces: the first admin comes from
     * `php artisan super-admin:create`.
     */
    public function run(SetupSyncer $syncer): void
    {
        $failed = collect($syncer->sync())->filter(fn ($result): bool => $result->status->value === 'failed');

        if ($failed->isNotEmpty()) {
            throw new RuntimeException('Setup sync failed: '.$failed->map(fn ($result): string => "{$result->dataset} ({$result->message})")->implode('; '));
        }
    }
}
