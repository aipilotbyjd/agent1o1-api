<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Moves existing balances into the wallet without changing what anyone
     * can spend:
     *
     * - each workspace's `topup_credits` becomes one never-expiring
     *   `migration` grant of the same size;
     * - every owner's oldest workspace (deleted ones included) becomes their
     *   free workspace, so existing free workspaces keep their allowance and
     *   only workspaces created from now on are affected by the one-per-owner
     *   rule.
     *
     * Period allowances need no backfill: `CreditWallet::ensureAllowance()`
     * creates each period's grant from `credits_limit` the first time it is
     * read, counting what the period already used.
     */
    public function up(): void
    {
        $now = now();

        DB::table('workspaces')
            ->where('topup_credits', '>', 0)
            ->select('id', 'topup_credits')
            ->orderBy('id')
            ->chunk(500, function ($workspaces) use ($now): void {
                DB::table('credit_grants')->insert($workspaces->map(fn (object $workspace): array => [
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $workspace->id,
                    'source' => 'migration',
                    'amount' => $workspace->topup_credits,
                    'remaining' => $workspace->topup_credits,
                    'priority' => 80,
                    'starts_at' => $now,
                    'idempotency_key' => 'backfill:topup:'.$workspace->id,
                    'reason' => 'Top-up balance before the credit wallet',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        DB::table('workspaces')
            ->whereNotNull('owner_id')
            ->select('owner_id', DB::raw('min(created_at) as first_created_at'))
            ->groupBy('owner_id')
            ->orderBy('owner_id')
            ->chunk(500, function ($owners): void {
                foreach ($owners as $owner) {
                    $workspaceId = DB::table('workspaces')
                        ->where('owner_id', $owner->owner_id)
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->value('id');

                    DB::table('users')
                        ->where('id', $owner->owner_id)
                        ->whereNull('free_workspace_id')
                        ->update(['free_workspace_id' => $workspaceId]);
                }
            });
    }

    public function down(): void
    {
        DB::table('credit_grants')->where('idempotency_key', 'like', 'backfill:%')->delete();
        DB::table('users')->update(['free_workspace_id' => null]);
    }
};
