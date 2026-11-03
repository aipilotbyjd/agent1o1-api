<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A run's running credit total, added to as each of its steps finishes, so
 * the per-run credit budget is one read instead of re-pricing every step the
 * run has taken (which grows with the square of a loop's length).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->unsignedBigInteger('credits_accrued')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('credits_accrued');
        });
    }
};
