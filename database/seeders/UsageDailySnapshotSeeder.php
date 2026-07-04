<?php

namespace Database\Seeders;

use App\Models\UsageDailySnapshot;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class UsageDailySnapshotSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->command->warn('UsageDailySnapshotSeeder: no workspace found — skipping.');

            return;
        }

        if (UsageDailySnapshot::where('workspace_id', $workspace->id)->exists()) {
            $this->command->info('UsageDailySnapshotSeeder: snapshots already exist — skipping.');

            return;
        }

        $days = 30;

        for ($i = $days; $i >= 1; $i--) {
            $execTotal = fake()->numberBetween(0, 80);
            $execFailed = fake()->numberBetween(0, (int) ($execTotal * 0.2));

            UsageDailySnapshot::create([
                'workspace_id' => $workspace->id,
                'snapshot_date' => now()->subDays($i)->toDateString(),
                'credits_used' => fake()->numberBetween(0, 400),
                'executions_total' => $execTotal,
                'executions_succeeded' => $execTotal - $execFailed,
                'executions_failed' => $execFailed,
                'nodes_executed' => $execTotal * fake()->numberBetween(3, 8),
                'ai_nodes_executed' => fake()->numberBetween(0, (int) ($execTotal * 0.4)),
            ]);
        }

        $this->command->info("UsageDailySnapshotSeeder: created {$days} daily snapshots.");
    }
}
