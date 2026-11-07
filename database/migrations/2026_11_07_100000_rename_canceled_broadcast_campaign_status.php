<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `BroadcastStatus::Canceled` ('canceled') became `Cancelled` ('cancelled'),
 * the spelling every other status enum uses (`RunStatus`, `AgentActionStatus`,
 * the Assistant statuses). Rewrites campaigns already stored with the old value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('broadcast_campaigns')->where('status', 'canceled')->update(['status' => 'cancelled']);
    }

    public function down(): void
    {
        DB::table('broadcast_campaigns')->where('status', 'cancelled')->update(['status' => 'canceled']);
    }
};
