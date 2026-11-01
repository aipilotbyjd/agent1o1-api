<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace's model choices: see `App\Models\Ai\WorkspaceModelSettings`.
 * At most one row per workspace; no row means no default and every model
 * allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_model_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('default_model_catalog_id')->nullable()->constrained('model_catalog')->nullOnDelete();
            $table->json('allowed_model_catalog_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_model_settings');
    }
};
