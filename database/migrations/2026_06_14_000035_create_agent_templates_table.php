<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->json('tags')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('system_prompt')->nullable();
            $table->string('llm_provider')->default('anthropic');
            $table->string('llm_model')->default('claude-sonnet-4-6');
            $table->json('llm_settings')->nullable();
            $table->json('tool_configs')->nullable();
            $table->json('example_conversations')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_templates');
    }
};
