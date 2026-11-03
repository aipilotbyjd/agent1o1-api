<?php

namespace App\Console\Commands\Admin;

use App\Models\Ai\AiCallEvent;
use Illuminate\Console\Command;

/**
 * Deletes AI call events past `model_operations.call_ledger.retention_days`,
 * in batches so a large backlog never holds a long lock. The daily facts
 * keep the history after that.
 */
class AiCallsPruneCommand extends Command
{
    protected $signature = 'ai-calls:prune';

    protected $description = 'Deletes AI call events older than the retention window.';

    private const int BATCH = 5000;

    public function handle(): int
    {
        $days = max(7, (int) config('model_operations.call_ledger.retention_days', 180));
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $ids = AiCallEvent::query()->where('occurred_at', '<', $cutoff)->limit(self::BATCH)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : AiCallEvent::query()->whereKey($ids)->delete();
        } while ($ids->count() === self::BATCH);

        $this->info("Deleted {$deleted} AI call event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
