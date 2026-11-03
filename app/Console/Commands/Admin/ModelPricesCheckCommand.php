<?php

namespace App\Console\Commands\Admin;

use App\Services\Ai\Operations\PriceBookReport;
use Illuminate\Console\Command;

/**
 * Lists every enabled route the platform pays for whose price is not
 * verified, and fails while there are any. Run it before switching
 * `billing.price_enforcement` to `enforce`, or as a deploy check.
 */
class ModelPricesCheckCommand extends Command
{
    protected $signature = 'model-prices:check {--days=7 : The window for call counts}';

    protected $description = 'Fails while any enabled platform route has no verified price.';

    public function handle(PriceBookReport $report): int
    {
        $data = $report->report(max(1, (int) $this->option('days')));
        $blocking = array_values(array_filter($data['routes'], fn (array $route): bool => $route['would_block']));

        $this->line("Price enforcement: {$data['enforcement']}");
        $this->line(sprintf(
            '%d enabled routes: %d verified, %d unverified, %d demo, %d unpriced.',
            $data['summary']['routes'], $data['summary']['verified'], $data['summary']['unverified'], $data['summary']['demo'], $data['summary']['unpriced'],
        ));

        if ($blocking === []) {
            $this->info('Every route the platform pays for has a verified price.');

            return self::SUCCESS;
        }

        $this->table(
            ['Model', 'Route', 'Price', 'Calls', 'Cost'],
            array_map(fn (array $route): array => [
                $route['model'],
                "{$route['provider']}:{$route['model_id']}",
                $route['price_status'],
                number_format($route['calls']),
                '$'.number_format($route['cost_usd'], 2),
            ], $blocking),
        );

        $this->error(count($blocking).' route(s) would be blocked under enforcement. Verify their prices first.');

        return self::FAILURE;
    }
}
