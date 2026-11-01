<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Something the knowledge base keeps in sync: a web page or a
        // connected app (Drive folder, Gmail label, repo, channel). Shared
        // with the workspace, or private to `owner_id`.
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('collection');
            $table->string('type');
            $table->string('name');
            $table->foreignUuid('connector_credential_id')->nullable()->constrained()->nullOnDelete();
            $table->json('config')->nullable();
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->string('sync_cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('documents_count')->default(0);
            $table->unsignedInteger('chunks_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'owner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};
