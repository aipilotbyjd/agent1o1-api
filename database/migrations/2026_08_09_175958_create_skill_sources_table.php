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
        // A GitHub repository the workspace's skills are synced from. Every
        // folder under `path` holding a `SKILL.md` becomes one skill; the
        // repository is the source of truth. No connected account means a
        // public repository read anonymously.
        Schema::create('skill_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('connector_credential_id')->nullable()->constrained()->nullOnDelete();
            $table->string('repo');
            $table->string('branch')->nullable();
            $table->string('path')->nullable();
            $table->boolean('is_shared')->default(true);
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->string('last_commit_sha', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('skills_count')->default(0);
            $table->boolean('two_way')->default(false);
            $table->json('sync_baseline')->nullable();
            $table->json('sync_conflicts')->nullable();
            $table->boolean('publish_once')->default(false);
            $table->boolean('repository_private')->nullable();
            $table->string('upstream_repo')->nullable();
            $table->string('upstream_branch')->nullable();
            $table->json('fork_request')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_sources');
    }
};
