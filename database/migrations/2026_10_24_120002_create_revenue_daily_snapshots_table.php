<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per day per plan, written nightly by `revenue:snapshot`. Money is
 * integer minor units. History is kept here so it survives a later plan price
 * change: re-deriving old days from current prices would rewrite the past.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('revenue_daily_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('plan_slug');
            $table->string('currency', 3);
            $table->unsignedInteger('subscribers')->default(0);
            $table->unsignedBigInteger('mrr')->default(0);
            $table->timestamps();

            $table->unique(['date', 'plan_slug', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_daily_snapshots');
    }
};
