<?php

namespace App\Console\Commands\SuperAdmin;

use App\Enums\Notifications\AlertSeverity;
use App\Models\SuperAdmin\AlertEvent;
use App\Services\Notifications\AdminAlerts;
use Illuminate\Console\Command;

/**
 * Scheduled daily: one message listing every alert still open, so nothing
 * stays acknowledged-and-forgotten. Sends nothing when all is quiet.
 */
class AlertsDigestCommand extends Command
{
    protected $signature = 'alerts:digest';

    protected $description = 'Sends a daily summary of the alerts that are still open.';

    public function handle(AdminAlerts $alerts): int
    {
        $open = AlertEvent::query()->active()->with('rule:id,name')->orderByDesc('last_fired_at')->get();

        if ($open->isEmpty()) {
            $this->info('No open alerts.');

            return self::SUCCESS;
        }

        $lines = $open->take(25)->map(fn (AlertEvent $event): string => "[{$event->severity->label()}/{$event->status}] {$event->title}")->implode("\n");

        $alerts->raise(
            'super-admin.alert.daily_digest',
            "{$open->count()} alert(s) still open",
            $lines,
            ['open' => $open->count(), 'unacknowledged' => $open->where('status', AlertEvent::STATUS_OPEN)->count()],
            $open->contains(fn (AlertEvent $event): bool => $event->severity === AlertSeverity::Critical) ? AlertSeverity::Critical : AlertSeverity::Info,
            throttleKey: 'alert-daily-digest:'.today()->toDateString(),
        );

        $this->info("Digest of {$open->count()} open alert(s) sent.");

        return self::SUCCESS;
    }
}
