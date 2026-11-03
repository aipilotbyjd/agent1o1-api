<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each charge earned in US dollars (see `CreditRevenue`): the revenue
 * side of the books, set against `ai_call_events.provider_cost_usd`. Charges
 * made before this column existed stay at zero; the analytics backfill
 * re-derives them from the plan in force.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->decimal('revenue_usd', 14, 6)->default(0)->after('debt_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn('revenue_usd');
        });
    }
};
