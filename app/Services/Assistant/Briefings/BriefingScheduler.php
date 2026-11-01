<?php

namespace App\Services\Assistant\Briefings;

use App\Enums\Assistant\AssistantBriefingType;
use App\Jobs\Assistant\RunBriefingJob;
use App\Models\Assistant\AssistantBriefingConfig;
use App\Models\Assistant\AssistantBriefingRun;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Starts the reports that are due: a Daily report runs once per local day,
 * on the owner's chosen days, from their chosen time on. Each day's run has
 * its own `run_key`, so a missed tick catches up and a doubled one is a
 * no-op.
 */
class BriefingScheduler
{
    public function startDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $started = 0;

        AssistantBriefingConfig::query()
            ->where('type', AssistantBriefingType::Daily)
            ->where('enabled', true)
            ->whereNull('paused_at')
            ->each(function (AssistantBriefingConfig $config) use ($now, &$started): void {
                $local = $now->copy()->tz($config->timezone());

                if (! in_array($local->dayOfWeekIso, $config->days(), true) || $local->format('H:i') < $config->time()) {
                    return;
                }

                if ($this->start($config, 'daily:'.$local->toDateString(), 'schedule') !== null) {
                    $started++;
                }
            });

        return $started;
    }

    public function runNow(AssistantBriefingConfig $config): ?AssistantBriefingRun
    {
        return $this->start($config, 'manual:'.Str::uuid(), 'manual');
    }

    private function start(AssistantBriefingConfig $config, string $runKey, string $trigger): ?AssistantBriefingRun
    {
        try {
            $run = $config->runs()->firstOrCreate(['run_key' => $runKey], ['trigger' => $trigger, 'window_start' => now()]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if (! $run->wasRecentlyCreated) {
            return null;
        }

        RunBriefingJob::dispatch($run);

        return $run;
    }
}
