<?php

namespace App\Console\Commands\Billing;

use App\Models\UsagePeriod;
use App\Services\Billing\CreditService;
use Illuminate\Console\Command;

class ReconcileRedisCommand extends Command
{
    protected $signature = 'billing:reconcile-redis';

    protected $description = 'Sync Redis credit cache from DB for recently-active workspaces.';

    public function __construct(private CreditService $creditService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $periods = UsagePeriod::where('is_current', true)
            ->where('updated_at', '>=', now()->subDays(7))
            ->with('workspace')
            ->get();

        foreach ($periods as $period) {
            $this->creditService->syncRedis($period->workspace);
        }

        $this->info("Reconciled Redis for {$periods->count()} workspace(s).");

        return self::SUCCESS;
    }
}
