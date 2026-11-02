<?php

namespace App\Console\Commands\Intelligence;

use App\Services\Intelligence\Experiments;
use App\Services\Intelligence\ForecastService;
use Illuminate\Console\Command;

/**
 * Records today's forecasts, fills in the actuals of past ones, and stops any
 * experiment whose variant fell under its guard.
 */
class IntelligencePredictCommand extends Command
{
    protected $signature = 'intelligence:predict';

    protected $description = 'Records forecasts, scores past ones against what happened, and guards running experiments.';

    public function handle(ForecastService $forecasts, Experiments $experiments): int
    {
        $result = $forecasts->record();
        $stopped = $experiments->guard();

        $this->info("Recorded {$result['recorded']} forecast point(s), filled {$result['actuals']} actual(s).");

        foreach ($stopped as $key) {
            $this->warn("Stopped experiment [{$key}]: a variant fell under its guard.");
        }

        return self::SUCCESS;
    }
}
