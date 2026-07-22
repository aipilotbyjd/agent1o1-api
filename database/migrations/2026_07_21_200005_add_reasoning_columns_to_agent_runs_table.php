<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give agent_runs first-class columns for the reasoning trace (the plan the
 * agent drafted, the reflections it made) and the estimated dollar cost that
 * the daily cost-budget guardrail enforces against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->json('plan')->nullable()->after('output');
            $table->json('reflections')->nullable()->after('plan');
            $table->decimal('estimated_cost', 12, 6)->nullable()->after('total_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn(['plan', 'reflections', 'estimated_cost']);
        });
    }
};
