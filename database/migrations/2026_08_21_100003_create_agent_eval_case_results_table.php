<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the agent actually answered for one case, and how each of that case's
 * assertions graded.
 *
 * A row per case rather than one JSON blob on the eval run: this is the unit
 * a person reads ("which case regressed, and what did it say?"), the unit
 * credits are charged against (`CreditTransactionType::EvalCase`, whose
 * idempotency key is this row's id), and the unit you want to be able to
 * query across runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_eval_case_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_eval_run_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_eval_case_id')->constrained()->cascadeOnDelete();
            $table->longText('output')->nullable();
            $table->boolean('passed')->default(false);
            $table->json('assertions')->nullable();
            $table->json('usage')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['agent_eval_run_id', 'passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_eval_case_results');
    }
};
