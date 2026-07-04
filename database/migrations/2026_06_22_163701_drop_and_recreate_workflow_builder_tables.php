<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workflow_builder_messages');
        Schema::dropIfExists('workflow_builder_sessions');

        Schema::create('workflow_builder_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('conversation_id')->nullable()->index();
            $table->string('title')->default('Untitled workflow');
            $table->json('nodes_draft')->default('[]');
            $table->json('edges_draft')->default('[]');
            $table->unsignedInteger('draft_lock_version')->default(0);
            $table->enum('status', ['active', 'completed', 'archived', 'failed'])->default('active');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'status']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('workflow_builder_draft_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')
                ->constrained('workflow_builder_sessions')
                ->cascadeOnDelete();
            $table->uuid('triggered_by')->nullable()->index();
            $table->json('nodes_snapshot');
            $table->json('edges_snapshot');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });

        Schema::create('workflow_builder_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')
                ->constrained('workflow_builder_sessions')
                ->cascadeOnDelete();
            $table->uuid('draft_version_id')->nullable()->index();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->json('actions')->nullable();
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                ->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_builder_messages');
        Schema::dropIfExists('workflow_builder_draft_versions');
        Schema::dropIfExists('workflow_builder_sessions');
    }
};
