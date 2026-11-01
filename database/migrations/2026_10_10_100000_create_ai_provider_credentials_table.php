<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace's own API key for an AI provider (bring your own key). See
 * `App\Models\Ai\AiProviderCredential` and docs/BYOK_PLAN.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('execution_provider');
            $table->string('scope')->default('team');
            $table->boolean('is_default')->default(false);
            $table->string('name')->nullable();
            $table->text('data');
            $table->string('key_hint', 32)->nullable();
            $table->string('validation_status')->default('unvalidated');
            $table->string('validation_message', 500)->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'execution_provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_credentials');
    }
};
