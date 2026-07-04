<?php

namespace App\Engine\Polling;

use App\Contracts\TriggerExecutor;
use App\Engine\Polling\Executors\GenericApiPollingExecutor;
use App\Engine\Polling\Executors\GmailExecutor;
use App\Engine\Polling\Executors\GoogleSheetsExecutor;
use App\Models\Trigger;

class PollingRegistry
{
    /** @var array<string, class-string<TriggerExecutor>> Map of provider → executor class */
    private const EXECUTORS = [
        'generic' => GenericApiPollingExecutor::class,
        'api' => GenericApiPollingExecutor::class,
        'gmail' => GmailExecutor::class,
        'google_sheets' => GoogleSheetsExecutor::class,
    ];

    public function executorFor(Trigger $trigger): ?TriggerExecutor
    {
        $provider = $trigger->settings['polling_provider'] ?? 'generic';
        $class = self::EXECUTORS[$provider] ?? GenericApiPollingExecutor::class;

        return app($class);
    }
}
