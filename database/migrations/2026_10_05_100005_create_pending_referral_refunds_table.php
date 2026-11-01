<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A refund webhook can arrive before the payment it refunds has been
 * recorded. It waits here until the payment shows up, instead of being
 * discarded and letting that payment earn a reward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_referral_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_intent_id')->unique();
            $table->boolean('fully_refunded');
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_referral_refunds');
    }
};
