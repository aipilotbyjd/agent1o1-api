<?php

namespace App\Console\Commands\Intelligence;

use App\Services\Intelligence\RecommendationEngine;
use App\Services\SuperAdmin\Alerts\SuperAdminAlerter;
use Illuminate\Console\Command;

/**
 * Re-runs every recommendation rule, and alerts the operators when a new
 * critical one appears.
 */
class IntelligenceRecommendCommand extends Command
{
    protected $signature = 'intelligence:recommend';

    protected $description = 'Evaluates the recommendation rules against the latest numbers.';

    public function handle(RecommendationEngine $engine, SuperAdminAlerter $alerter): int
    {
        $result = $engine->evaluate();

        if ($result['created'] > 0) {
            $alerter->raise('unit_economics', "{$result['created']} new recommendation(s) need a decision.", ['open' => $result['open']]);
        }

        $this->info("{$result['open']} open, {$result['created']} new, {$result['resolved']} resolved.");

        return self::SUCCESS;
    }
}
