<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\CreditReservations;
use Illuminate\Console\Command;

/**
 * Deletes credit holds that were released or expired more than a day ago.
 * An expired hold already stops counting against the balance; this only
 * keeps the table small.
 */
class PruneCreditReservationsCommand extends Command
{
    protected $signature = 'credits:prune-reservations';

    protected $description = 'Deletes released and expired credit holds.';

    public function handle(CreditReservations $reservations): int
    {
        $this->info($reservations->prune().' credit hold(s) deleted.');

        return self::SUCCESS;
    }
}
