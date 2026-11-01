<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files an agent produces during a session: versioned by `group_id` so a
 * re-generated file (e.g. an updated report) becomes a new row sharing the
 * same group rather than overwriting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();
            $table->foreignUuid('assistant_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('group_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->json('metadata')->nullable();
            $table->foreignUuid('agent_message_id')->nullable()->constrained('agent_messages')->nullOnDelete();
            $table->foreignUuid('workflow_builder_message_id')->nullable()->constrained('workflow_builder_messages')->nullOnDelete();
            $table->softDeletes();
            $table->string('general_access')->default('restricted');
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['agent_session_id', 'filename']);
            $table->unique(['group_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
