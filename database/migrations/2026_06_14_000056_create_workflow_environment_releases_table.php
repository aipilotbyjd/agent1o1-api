<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_environment_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('environment_id')->constrained('workspace_environments')->cascadeOnDelete();
            $table->foreignUuid('version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('released_at');
            $table->timestamps();

            $table->index(['environment_id', 'workflow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_environment_releases');
    }
};
