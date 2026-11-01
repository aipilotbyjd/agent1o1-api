<?php

namespace App\Console\Commands\Admin;

use App\Models\Ai\ModelCatalog;
use App\Services\Ai\Operations\ModelLifecycle;
use App\Services\SuperAdmin\Audit\SuperAdminAuditLogger;
use Illuminate\Console\Command;

/**
 * Retires the models whose announced date has come, moving what uses them to
 * the replacement. Scheduled daily; see `ModelLifecycle::retireDue()`.
 */
class ModelCatalogRetireDueCommand extends Command
{
    protected $signature = 'model-catalog:retire-due';

    protected $description = 'Retires models whose retirement date has passed and moves their users to the replacement.';

    public function handle(ModelLifecycle $lifecycle, SuperAdminAuditLogger $audit): int
    {
        $result = $lifecycle->retireDue();

        foreach ($result['retired'] as $slug => $summary) {
            $audit->record(
                null,
                'models.retired',
                ModelCatalog::query()->where('slug', $slug)->first(),
                after: $summary,
                reason: 'Retirement date reached',
                actorLabel: 'scheduler',
            );

            $this->info("Retired [{$slug}] in favour of [{$summary['replacement']}] ({$summary['agents']} agents, {$summary['assistants']} assistants, {$summary['workspaces']} workspace settings moved).");
        }

        foreach ($result['failed'] as $slug => $message) {
            $this->error("Could not retire [{$slug}]: {$message}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
