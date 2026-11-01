<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a workspace's own AI provider keys and the platform's keys are mixed:
 * see `App\Models\Ai\WorkspaceAiKeyPolicy`. At most one row per
 * workspace; no row means the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_ai_key_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('platform_usage')->default('fallback');
            $table->boolean('allow_personal_keys')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_ai_key_policies');
    }
};
