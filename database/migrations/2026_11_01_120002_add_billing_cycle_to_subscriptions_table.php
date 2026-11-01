<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The subscription's current billing cycle, copied from Stripe on every
     * subscription webhook. Usage periods follow it, so credits refill on
     * the billing date rather than on the 1st of the month: subscribing on
     * the 28th no longer earns a second allowance three days later.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('current_period_starts_at')->nullable()->after('ends_at');
            $table->timestamp('current_period_ends_at')->nullable()->after('current_period_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['current_period_starts_at', 'current_period_ends_at']);
        });
    }
};
