<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->longText('instructions');
            $table->string('model')->default('claude-sonnet-4-6');
            $table->string('provider')->default('anthropic');
            $table->unsignedInteger('max_steps')->default(15);
            $table->unsignedInteger('timeout_seconds')->default(180);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->foreignUuid('default_workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
