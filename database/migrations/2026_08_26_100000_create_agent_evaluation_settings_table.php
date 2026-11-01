<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per `Agent`, whether/how its live `AgentSession`s are
 * automatically graded after each turn. See
 * `Services\Agents\SessionEvaluator` and
 * docs/gumloop/output/raw/core-concepts/evaluations.md, the feature this
 * mirrors.
 *
 * `criteria`/`tags`/`data_points` are JSON arrays rather than normalized
 * tables (the same choice `agent_eval_cases.assertions` made) because
 * nothing here is ever queried by its own columns; it is only ever read or
 * written whole, as one agent's configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_evaluation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('model')->nullable();
            $table->boolean('sentiment_enabled')->default(true);
            $table->boolean('sentiment_affects_grade')->default(false);
            $table->text('sentiment_guidance')->nullable();
            $table->boolean('suggest_tags_automatically')->default(false);
            $table->json('criteria')->nullable();
            $table->json('tags')->nullable();
            $table->json('data_points')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_evaluation_settings');
    }
};
