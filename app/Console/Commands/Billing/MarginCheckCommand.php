<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\MarginFloor;
use Illuminate\Console\Command;

/**
 * Lists every plan interval, credit pack and the overage rate with what a
 * credit earns, and fails when any of them earns less than the margin floor.
 * Run it after a pricing change, or as a deploy check against production.
 */
class MarginCheckCommand extends Command
{
    protected $signature = 'billing:margin-check {--failing : Only list the offers below the floor}';

    protected $description = 'Checks that every plan, pack and the overage rate earn more per credit than a credit costs.';

    public function handle(MarginFloor $floor): int
    {
        $rows = $this->option('failing') ? $floor->violations() : $floor->report();

        $this->table(
            ['Kind', 'Offer', 'Interval', 'Price', 'Credits', '$/credit', 'Net $/credit', 'Floor', 'Margin', 'OK'],
            array_map(fn (array $row): array => [
                $row['kind'],
                $row['subject'],
                $row['interval'] ?? '–',
                '$'.number_format($row['price_usd'], 2),
                number_format($row['credits']),
                number_format($row['usd_per_credit'], 5),
                number_format($row['net_usd_per_credit'], 5),
                number_format($row['floor_usd_per_credit'], 5),
                $row['margin_percent'].'%',
                $row['passes'] ? 'yes' : 'NO',
            ], $rows),
        );

        $failing = count($floor->violations());

        if ($failing > 0) {
            $this->error("{$failing} offer(s) earn less per credit than the margin floor.");

            return self::FAILURE;
        }

        $this->info('Every offer earns more per credit than it costs.');

        return self::SUCCESS;
    }
}
