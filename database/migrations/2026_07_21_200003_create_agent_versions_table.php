<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioning & rollback (roadmap item 10): an immutable snapshot of an agent's
 * behaviour-defining config (instructions, model, tool config, skills, advanced
 * settings) taken on every save, so edits can be diffed and rolled back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('label')->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['agent_id', 'version']);
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_versions');
    }
};
