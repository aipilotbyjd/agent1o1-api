<?php

namespace App\Jobs\Account;

use App\Enums\Queue;
use App\Models\Account\DataExport;
use App\Services\Account\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Builds one requested personal data export and emails the account once it
 * is ready. Runs on the long-running connection: an account with years of
 * agent history can take longer than the default queue's retry window.
 */
class BuildDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public string $dataExportId)
    {
        $this->onConnection(config('queue.long_running_connection'));
        $this->onQueue(Queue::LongRunning->value);
    }

    public function handle(DataExportService $exports): void
    {
        $export = DataExport::query()->with('user')->find($this->dataExportId);

        if ($export === null || $export->user === null || $export->status->isInFlight() === false) {
            return;
        }

        $exports->build($export);
    }

    public function failed(?Throwable $exception): void
    {
        $export = DataExport::query()->find($this->dataExportId);

        if ($export !== null && $exception !== null && $export->status->isInFlight()) {
            app(DataExportService::class)->markFailed($export, $exception);
        }
    }
}
