<?php

namespace App\Jobs\Agents;

use App\Enums\Agents\SkillSourceStatus;
use App\Models\Agents\SkillSource;
use App\Services\Agents\Skills\SkillSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Syncs one skill source from its repository. One at a time per source.
 * `force` re-reads the repository even if its branch hasn't moved.
 */
class SyncSkillSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(public SkillSource $source, public bool $force = false) {}

    public function failed(?Throwable $exception): void
    {
        SkillSource::query()->whereKey($this->source->id)->update([
            'status' => SkillSourceStatus::Failed,
            'last_error' => Str::limit($exception?->getMessage() ?? 'Skill sync did not finish. Try syncing again.', 500),
        ]);
    }

    public function uniqueId(): string
    {
        return $this->source->id;
    }

    public function handle(SkillSync $sync): void
    {
        $sync->sync($this->source, $this->force);
    }
}
