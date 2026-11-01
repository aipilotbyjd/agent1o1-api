<?php

namespace App\Listeners\Workflows;

use App\Events\Runs\RunFailed;
use App\Notifications\Workspace\RunFailedNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

class NotifyOnRunFailure
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(RunFailed $event): void
    {
        $run = $event->run;

        Log::warning('Run failed.', [
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'error' => $run->error,
        ]);

        $workspace = $run->workspace;

        if ($workspace === null) {
            return;
        }

        $this->dispatcher->dispatch(
            $this->dispatcher->ownersAndAdmins($workspace),
            new RunFailedNotification($workspace, $run),
        );
    }
}
