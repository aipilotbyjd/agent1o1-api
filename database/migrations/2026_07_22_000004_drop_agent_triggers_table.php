<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract step: remove the legacy agent_triggers table. Its rows were copied
 * into the unified `triggers` table by the preceding data migration, and all
 * code now reads agent triggers through the polymorphic Trigger model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_triggers');
    }

    public function down(): void
    {
        Schema::create('agent_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('config')->nullable();
            $table->text('initial_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });
    }
};
