<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the Data Store node remembers between runs: one row per key,
     * inside a named store, inside a workspace. Never shared across
     * workspaces, and a row can expire.
     */
    public function up(): void
    {
        Schema::create('workspace_data_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('store', 100)->default('default');
            $table->string('key', 255);
            $table->json('value')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'store', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_data_entries');
    }
};
