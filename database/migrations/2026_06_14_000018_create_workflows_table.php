<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 10)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_locked')->default(false);
            // Populated after workflow_versions exists; FK added in that migration.
            $table->uuid('current_version_id')->nullable();
            $table->uuid('error_workflow_id')->nullable();
            $table->unsignedInteger('max_concurrent_executions')->default(1);
            $table->unsignedBigInteger('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->decimal('success_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
            $table->index(['workspace_id', 'folder_id']);
        });

        // Self-referential FK added after the table and its primary key are committed.
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreign('error_workflow_id')->references('id')->on('workflows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
