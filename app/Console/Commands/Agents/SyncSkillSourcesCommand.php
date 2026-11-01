<?php

namespace App\Console\Commands\Agents;

use App\Enums\Agents\SkillSourceStatus;
use App\Jobs\Agents\SyncSkillSourceJob;
use App\Models\Agents\SkillSource;
use Illuminate\Console\Command;

class SyncSkillSourcesCommand extends Command
{
    protected $signature = 'skills:sync-sources';

    protected $description = 'Queue a check of every skill repository; only ones whose branch moved are re-imported.';

    public function handle(): int
    {
        $count = 0;

        SkillSource::query()
            ->whereNot('status', SkillSourceStatus::Syncing->value)
            ->each(function (SkillSource $source) use (&$count): void {
                SyncSkillSourceJob::dispatch($source);
                $count++;
            });

        $this->info("Queued {$count} skill source sync(s).");

        return self::SUCCESS;
    }
}
