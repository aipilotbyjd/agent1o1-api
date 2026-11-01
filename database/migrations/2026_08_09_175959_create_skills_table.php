<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reusable instruction/knowledge snippet an Agent can use: see
 * docs/AGENTS_PLAN.md's "Skills" section. `version` increments on
 * instruction edits, same "don't let the model behind an in-flight session
 * change under it" reasoning as `Agent`/`agent_versions`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('skill_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_path')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->json('tags')->nullable();
            $table->longText('instructions');
            $table->boolean('is_shared')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->text('origin_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'is_shared']);
            $table->index(['workspace_id', 'category']);
            $table->index(['skill_source_id', 'source_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
