<?php

namespace App\Jobs\Connectors;

use App\Enums\Queue;
use App\Models\Connectors\ConnectorCredential;
use App\Services\Connectors\ConnectorCredentialTester;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background twin of the Apps page's "Test" button: catches a connection
 * the provider has revoked before a workflow run trips over it.
 */
class CheckConnectorCredentialHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public ConnectorCredential $credential)
    {
        $this->onQueue(Queue::Maintenance->value);
    }

    public function handle(ConnectorCredentialTester $tester): void
    {
        $tester->test($this->credential);
    }
}
