<?php

namespace App\Console\Commands\SuperAdmin;

use App\Enums\Notifications\AlertSeverity;
use App\Enums\Operations\TaskStatus;
use App\Services\Notifications\AdminAlerts;
use App\Services\SuperAdmin\Operations\OperationsBoard;
use App\Services\SuperAdmin\Operations\TaskState;
use Illuminate\Console\Command;

/**
 * Scheduled each morning: one message listing the checklist items that need
 * attention, so a task cannot be forgotten. Sends nothing when all is done.
 */
class OperationsDigestCommand extends Command
{
    protected $signature = 'operations:digest';

    protected $description = 'Sends a daily reminder of the checklist items that need attention.';

    public function handle(OperationsBoard $board, AdminAlerts $alerts): int
    {
        $needs = $board->states()->filter(fn (TaskState $state): bool => $state->status->needsAttention())->values();

        if ($needs->isEmpty()) {
            $this->info('Nothing on the checklist needs attention.');

            return self::SUCCESS;
        }

        $overdue = $needs->where('status', TaskStatus::Overdue)->count();

        $lines = $needs->take(25)->map(fn (TaskState $state): string => '['.ucfirst($state->status->value).'] '.$state->task->title.($state->detail ? ': '.$state->detail : ''))->implode("\n");

        $alerts->raise(
            'operations.daily_digest',
            "{$needs->count()} checklist item(s) need attention",
            $lines,
            ['needs_attention' => $needs->count(), 'overdue' => $overdue],
            $overdue > 0 ? AlertSeverity::Warning : AlertSeverity::Info,
            throttleKey: 'operations-daily-digest:'.today()->toDateString(),
        );

        $this->info("Reminder for {$needs->count()} item(s) sent.");

        return self::SUCCESS;
    }
}
