<?php

namespace App\Console\Commands\Agents;

use App\Enums\Agents\KnowledgeSourceStatus;
use App\Jobs\Agents\SyncKnowledgeSourceJob;
use App\Models\Agents\KnowledgeSource;
use Illuminate\Console\Command;

class SyncKnowledgeSourcesCommand extends Command
{
    protected $signature = 'knowledge:sync-sources';

    protected $description = 'Queue a sync for every knowledge source not synced within the interval.';

    public function handle(): int
    {
        $count = 0;

        KnowledgeSource::query()
            ->whereNot('status', KnowledgeSourceStatus::Syncing->value)
            ->where(fn ($query) => $query
                ->whereNull('last_synced_at')
                ->orWhere('last_synced_at', '<=', now()->subMinutes((int) config('knowledge_base.sources.sync_every_minutes'))))
            ->each(function (KnowledgeSource $source) use (&$count): void {
                SyncKnowledgeSourceJob::dispatch($source);
                $count++;
            });

        $this->info("Queued {$count} knowledge source sync(s).");

        return self::SUCCESS;
    }
}
