<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-click AI provider connection in flight: created when the member is
 * sent to the provider, claimed (deleted) once when they come back. See
 * `App\Models\Ai\AiProviderConnectState`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_connect_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('execution_provider');
            $table->string('scope');
            $table->string('state', 64)->unique();
            $table->text('code_verifier');
            $table->string('redirect_uri', 2048);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_connect_states');
    }
};
