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
            $execTotal = random_int(0, 80);
            $execFailed = random_int(0, (int) ($execTotal * 0.2));

            UsageDailySnapshot::create([
                'workspace_id' => $workspace->id,
                'snapshot_date' => now()->subDays($i)->toDateString(),
                'credits_used' => random_int(0, 400),
                'executions_total' => $execTotal,
                'executions_succeeded' => $execTotal - $execFailed,
                'executions_failed' => $execFailed,
                'nodes_executed' => $execTotal * random_int(3, 8),
                'ai_nodes_executed' => random_int(0, (int) ($execTotal * 0.4)),
            ]);
        }

        $this->command->info("UsageDailySnapshotSeeder: created {$days} daily snapshots.");
    }
}
