<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tool-binding pivot: ported from the old project's `agent_model_node`
 * (see docs/AGENTS_PLAN.md's verbatim design-comment quote). Keyed on a
 * `node_type` string (the same string `workflow_nodes.type` uses, resolved
 * through the same `NodeRegistry`, including `custom:{id}`) rather than a
 * DB foreign key, since built-in nodes are PHP classes with no row of their
 * own (docs/WORKFLOWS_PLAN.md's node-catalog split).
 *
 * `config`/`exposed_fields` are the whole security boundary: config values
 * bound here are merged over whatever the model supplies at tool-call time,
 * so a credential or a fixed channel can never be chosen by the LLM.
 * `exposed_fields` is the allow-list of config fields the model may fill:
 * null means every field not already bound in `config`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_node', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->string('node_type');
            $table->json('config')->nullable();
            $table->json('exposed_fields')->nullable();
            $table->json('approval_policy')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'node_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_node');
    }
};
