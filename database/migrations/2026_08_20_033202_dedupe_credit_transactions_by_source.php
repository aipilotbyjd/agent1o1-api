<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clears the way for the `(source_type, source_id)` unique index added in the
 * next migration. Before charges were idempotent a retried
 * `RecordRunCreditUsage` job could bill the same `NodeRun` twice, so any
 * install with such a duplicate would fail to add the index. Keeps the
 * earliest row per source (the one whose credits were counted into
 * `usage_periods.credits_used` first) and drops the later re-charges.
 *
 * Irreversible by design: `down()` cannot resurrect rows that should never
 * have been written.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateIds = DB::table('credit_transactions')
            ->select('source_type', 'source_id')
            ->groupBy('source_type', 'source_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->flatMap(function (object $group): array {
                $keepId = DB::table('credit_transactions')
                    ->where('source_type', $group->source_type)
                    ->where('source_id', $group->source_id)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->value('id');

                return DB::table('credit_transactions')
                    ->where('source_type', $group->source_type)
                    ->where('source_id', $group->source_id)
                    ->where('id', '!=', $keepId)
                    ->pluck('id')
                    ->all();
            });

        $duplicateIds->chunk(500)->each(
            fn ($chunk) => DB::table('credit_transactions')->whereIn('id', $chunk->all())->delete()
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deleted duplicate charges are not recoverable, and re-creating them
        // would re-introduce the double-billing this migration exists to fix.
    }
};
