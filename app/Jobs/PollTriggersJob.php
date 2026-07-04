<?php

namespace App\Jobs;

use App\Models\Trigger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollTriggersJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('triggers');
    }

    public function handle(): void
    {
        Trigger::query()
            ->where('type', 'polling')
            ->where('is_active', true)
            ->where('is_paused', false)
            ->where(function ($query) {
                $query->whereNull('polling_next_check_at')
                    ->orWhere('polling_next_check_at', '<=', now());
            })
            ->chunk(100, function ($triggers) {
                foreach ($triggers as $trigger) {
                    PollSingleTriggerJob::dispatch($trigger->id)->onQueue('triggers');

                    $trigger->update([
                        'polling_last_check_at' => now(),
                        'polling_next_check_at' => now()->addSeconds(
                            max(30, $trigger->polling_interval_seconds ?? 300),
                        ),
                    ]);
                }
            });
    }
}
