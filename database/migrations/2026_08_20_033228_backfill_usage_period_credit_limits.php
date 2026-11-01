<?php

use App\Models\Billing\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sizes already-open usage periods that were created with a `null` (i.e.
 * unlimited) `credits_limit` because their workspace had no subscription.
 * `Workspace::currentUsagePeriod()` now falls back to the default plan, but
 * it only applies that on *creation*: rows opened before this change would
 * otherwise keep billing unlimited until the next month rolled over.
 *
 * Only periods that are still open are touched: closed ones are historical
 * records, and rewriting their limit would misstate what was actually
 * allowed at the time.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $plan = Plan::default();

        if ($plan === null) {
            return;
        }

        DB::table('usage_periods')
            ->whereNull('credits_limit')
            ->whereNull('plan_id')
            ->where('ends_at', '>', now())
            ->update([
                'plan_id' => $plan->id,
                'credits_limit' => $plan->credits_monthly,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $plan = Plan::default();

        if ($plan === null) {
            return;
        }

        DB::table('usage_periods')
            ->where('plan_id', $plan->id)
            ->whereNull('subscription_id')
            ->where('ends_at', '>', now())
            ->update([
                'plan_id' => null,
                'credits_limit' => null,
                'updated_at' => now(),
            ]);
    }
};
