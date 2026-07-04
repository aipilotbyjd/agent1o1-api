<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('node_categories')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $table->string('type', 100)->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 50);
            $table->string('color', 20);
            $table->string('node_kind', 20);
            $table->json('config_schema');
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->string('credential_type', 100)->nullable();
            $table->decimal('cost_hint_usd', 10, 4)->nullable();
            $table->unsignedInteger('latency_hint_ms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->string('docs_url', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_custom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
