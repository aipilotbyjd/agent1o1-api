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
        Schema::create('agent_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null workspace_id = a global/system template visible to every workspace.
            $table->foreignUuid('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->nullable();
            // {instructions, provider, model, temperature, settings, tool_bindings: [...], workflow_ids: [...], skill_ids: [...]}
            $table->json('config');
            $table->string('visibility')->default('private');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['visibility', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_templates');
    }
};
