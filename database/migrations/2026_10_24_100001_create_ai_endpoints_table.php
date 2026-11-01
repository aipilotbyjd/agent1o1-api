<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-defined OpenAI-compatible endpoints: a model the platform runs
 * itself (a vLLM or Ollama box, a private gateway) or a provider that has no
 * entry in `config/ai.php`. Registered as an AI provider at runtime (see
 * `OperatorEndpoints`), so adding one needs no deploy. The slug is the
 * provider name routes refer to. The optional per-minute limits protect a
 * server of your own from one tenant using all of it (see `EndpointRateLimiter`).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_key')->nullable();
            $table->boolean('is_enabled')->default(true);
            // Calls the platform will send per minute: from one workspace, and from all together. Null is no limit.
            $table->unsignedInteger('workspace_requests_per_minute')->nullable();
            $table->unsignedInteger('total_requests_per_minute')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('last_check_ok')->nullable();
            $table->string('last_check_message', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_endpoints');
    }
};
