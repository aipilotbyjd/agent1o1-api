<?php

namespace App\Console\Commands\Agents;

use App\Jobs\Agents\RunReflectionJob;
use App\Models\Agents\ReflectionSettings;
use Cron\CronExpression;
use Illuminate\Console\Command;

/**
 * Dispatches one `RunReflectionJob` per agent whose reflection schedule is
 * due: mirrors `Triggers\RunDueTriggersCommand`'s "write/queue due work and
 * return" shape rather than running analysis inline.
 */
class RunDueReflectionsCommand extends Command
{
    protected $signature = 'reflections:run-due';

    protected $description = 'Queue a reflection run for every agent whose reflection schedule is due.';

    public function handle(): int
    {
        $now = now();
        $queued = 0;

        // Due is judged from `next_run_at`, not a fresh `isDue($now)` check
        // against the cron expression: this command itself only runs every
        // few minutes, so gating on the stored watermark (rather than exact
        // minute-precision matching) is what makes "due" independent of
        // landing on the schedule's exact minute.
        ReflectionSettings::query()
            ->where('is_enabled', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->with('agent')
            ->each(function (ReflectionSettings $settings) use ($now, &$queued): void {
                if ($settings->agent === null) {
                    return;
                }

                RunReflectionJob::dispatch($settings->agent);

                $settings->forceFill([
                    'next_run_at' => (new CronExpression($settings->schedule_cron))->getNextRunDate($now),
                ])->save();

                $queued++;
            });

        $this->info("Queued {$queued} reflection run(s).");

        return self::SUCCESS;
    }
}
