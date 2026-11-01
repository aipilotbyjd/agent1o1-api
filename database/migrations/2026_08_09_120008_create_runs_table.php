<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('runs', function (Blueprint $table) {
            // The primary key is declared before the self-referencing foreign key below: PostgreSQL
            // needs the key to exist when the constraint is added, and `->primary()` on the column
            // would be added after it.
            $table->uuid('id');
            $table->primary('id');
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('runnable');
            $table->foreignUuid('workflow_id')->nullable()->constrained('workflows')->cascadeOnDelete();
            $table->foreignUuid('workflow_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
            $table->uuid('parent_run_id')->nullable();

            // FK to node_runs added once that table exists: see
            // create_node_runs_table / the parent_node_id foreign-key follow-up
            // migration, same "column now, constraint later" pattern the
            // triggers table already uses for credential_id.
            $table->uuid('parent_node_id')->nullable();

            $table->unsignedInteger('loop_index')->nullable();
            $table->uuid('environment_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('trigger_type')->default('manual');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignUuid('retried_from_run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->json('agent_context')->nullable();
            // The trigger node a trigger-fired run starts from. Null runs
            // start at every entry node, as they always have.
            $table->string('entry_node_key')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['parent_node_id', 'loop_index']);
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->foreign('parent_run_id')->references('id')->on('runs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
