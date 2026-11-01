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
        Schema::create('workflow_publish_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->json('graph');
            $table->json('editor_graph');
            $table->string('graph_fingerprint', 64);
            $table->foreignUuid('base_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignUuid('published_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('require_publish_approval')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('require_publish_approval');
        });

        Schema::dropIfExists('workflow_publish_requests');
    }
};
